<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\DeviceDetail;
use Antareja\Models\DeviceLink;
use Antareja\Models\Site;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Ringkasan infrastruktur wireless dari sensor wireless poller (SNMP per vendor).
 * Tidak semua vendor/model mengekspos metrik yang sama: kolom tanpa data ditampilkan "—".
 */
class WirelessController extends Controller
{
    public const WIRELESS_CATEGORIES = ['wireless-ap', 'wireless-controller', 'ptp', 'ptmp'];

    /** Metrik yang ditampilkan, urut kolom. [label, satuan, agregasi] */
    public const METRICS = [
        'clients' => ['Client', '', 'sum'],
        'rssi' => ['RSSI', 'dBm', 'min'],
        'snr' => ['SNR', 'dB', 'min'],
        'frequency' => ['Frekuensi', 'MHz', 'first'],
        'channel' => ['Channel', '', 'first'],
        'rate' => ['Rate', 'bps', 'max'],
        'capacity' => ['Kapasitas', '%', 'min'],
        'ccq' => ['CCQ', '%', 'min'],
        'quality' => ['Kualitas', '%', 'min'],
        'distance' => ['Jarak', 'km', 'max'],
        'utilization' => ['Utilisasi', '%', 'max'],
    ];

    /** Ambang peringatan sederhana untuk sorotan tabel. */
    public const WARN = ['rssi' => -75, 'snr' => 20, 'ccq' => 80, 'quality' => 70, 'capacity' => 50];

    public function index(Request $request): View
    {
        $allowed = Device::hasAccess($request->user())->pluck('device_id');

        $sensors = DB::table('wireless_sensors')->where('sensor_deleted', 0)->whereIn('device_id', $allowed)
            ->whereIn('sensor_class', array_keys(self::METRICS))
            ->get(['device_id', 'sensor_class', 'sensor_current', 'sensor_descr', 'sensor_id'])
            ->groupBy('device_id');

        $categorized = DeviceDetail::whereIn('category', self::WIRELESS_CATEGORIES)->whereIn('device_id', $allowed)->active()->pluck('device_id');
        $deviceIds = $sensors->keys()->merge($categorized)->unique();

        $details = DeviceDetail::with(['device', 'site'])->whereIn('device_id', $deviceIds)
            ->when($request->filled('site'), fn ($q) => $q->where('site_id', (int) $request->input('site')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->get()->filter(fn ($d) => $d->device)
            ->when($request->input('state') === 'down', fn ($c) => $c->filter(fn ($d) => ! $d->device->status))
            ->sortBy(fn ($d) => [$d->site?->code ?? 'zzz', $d->device->displayName()]);

        $rows = $details->map(function (DeviceDetail $d) use ($sensors) {
            $values = [];
            foreach (self::METRICS as $class => [$label, $unit, $agg]) {
                $vals = ($sensors[$d->device_id] ?? collect())->where('sensor_class', $class)->pluck('sensor_current')->filter(fn ($v) => $v !== null);
                $values[$class] = $vals->isEmpty() ? null : match ($agg) {
                    'sum' => $vals->sum(), 'min' => $vals->min(), 'max' => $vals->max(), default => $vals->first(),
                };
            }

            return ['detail' => $d, 'values' => $values, 'sensor_count' => ($sensors[$d->device_id] ?? collect())->count()];
        });

        // Pasangan PTP terverifikasi
        $pairs = DeviceLink::with(['aDevice', 'bDevice'])->where('status', DeviceLink::VERIFIED)
            ->whereIn('link_type', ['ptp-pair', 'ptmp-client'])->get()
            ->filter(fn ($l) => $l->aDevice && $l->bDevice);
        $partner = [];
        foreach ($pairs as $l) {
            $partner[$l->a_device_id][] = $l->bDevice;
            $partner[$l->b_device_id][] = $l->aDevice;
        }

        $all = $rows->pluck('detail.device');

        return view('antareja::wireless.index', [
            'rows' => $rows,
            'partner' => $partner,
            'totals' => [
                'devices' => $rows->count(),
                'online' => $all->where('status', 1)->count(),
                'offline' => $all->where('status', 0)->where('disabled', 0)->count(),
                'clients' => (int) $rows->sum(fn ($r) => $r['values']['clients'] ?? 0),
                'weak' => $rows->filter(fn ($r) => ($r['values']['rssi'] !== null && $r['values']['rssi'] < self::WARN['rssi'])
                    || ($r['values']['snr'] !== null && $r['values']['snr'] < self::WARN['snr']))->count(),
                'no_metrics' => $rows->where('sensor_count', 0)->count(),
            ],
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public static function format(string $class, float|int|null $v): string
    {
        if ($v === null) {
            return '—';
        }
        if ($class === 'rate') {
            return $v >= 1e9 ? round($v / 1e9, 2) . ' Gbps' : ($v >= 1e6 ? round($v / 1e6, 1) . ' Mbps' : round($v / 1e3) . ' kbps');
        }
        $unit = self::METRICS[$class][1];

        return rtrim(rtrim(number_format((float) $v, in_array($class, ['clients', 'channel', 'frequency'], true) ? 0 : 1, '.', ''), '0'), '.') . ($unit ? " {$unit}" : '');
    }

    public static function weak(string $class, float|int|null $v): bool
    {
        return $v !== null && isset(self::WARN[$class]) && $v < self::WARN[$class];
    }
}
