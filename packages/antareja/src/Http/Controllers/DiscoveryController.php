<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\DeviceDetail;
use Antareja\Services\InventoryQuery;
use Antareja\Services\InventorySync;
use Antareja\Services\VerificationService;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DiscoveryController extends Controller
{
    public function __construct(
        private readonly InventoryQuery $query,
        private readonly InventorySync $sync,
        private readonly VerificationService $verification,
    ) {
    }

    public function index(Request $request): View
    {
        $tab = in_array($request->input('tab'), ['pending', 'rejected', 'retired'], true) ? $request->input('tab') : 'pending';
        $request->merge(['verification' => $tab, 'sort' => $request->input('sort', 'discovered')]);

        $details = $this->query->build($request, $request->user())->paginate(50)->withQueryString();

        $base = DeviceDetail::query()->whereIn('device_id', Device::hasAccess($request->user())->select('device_id'));
        $counts = (clone $base)->selectRaw('verification_status, COUNT(*) AS c')->groupBy('verification_status')->pluck('c', 'verification_status');

        return view('antareja::discovery.index', [
            'details' => $details,
            'tab' => $tab,
            'counts' => $counts,
            'sites' => \Antareja\Models\Site::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function verify(Device $device): RedirectResponse
    {
        Gate::authorize('view', $device);
        $this->verification->verify($this->sync->ensure($device));

        return back()->with('antareja_success', "{$device->displayName()} diverifikasi sebagai inventaris resmi.");
    }

    public function reject(Request $request, Device $device): RedirectResponse
    {
        Gate::authorize('view', $device);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'alasan penolakan']);
        $disable = $request->boolean('disable_polling') && $request->user()->can('device.update');
        $this->verification->reject($this->sync->ensure($device), $data['reason'], $disable);

        return back()->with('antareja_success', "{$device->displayName()} ditolak" . ($disable ? ' dan polling dinonaktifkan.' : '.'));
    }

    public function retire(Request $request, Device $device): RedirectResponse
    {
        Gate::authorize('view', $device);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'alasan']);
        $disable = $request->boolean('disable_polling') && $request->user()->can('device.update');
        $this->verification->retire($this->sync->ensure($device), $data['reason'], $disable);

        return back()->with('antareja_success', "{$device->displayName()} dipensiunkan (retired).");
    }

    public function restore(Device $device): RedirectResponse
    {
        Gate::authorize('view', $device);
        $this->verification->restore($this->sync->ensure($device));

        return back()->with('antareja_success', "{$device->displayName()} dikembalikan ke antrean verifikasi.");
    }

    /**
     * Aksi massal untuk antrean verifikasi: set site / kategori, lalu verifikasi yang sudah memenuhi syarat.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'devices' => ['required', 'array', 'max:500'],
            'devices.*' => ['integer'],
            'action' => ['required', 'in:verify,assign'],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'category' => ['nullable', 'in:' . implode(',', array_keys(\Antareja\Support\Catalog::DEVICE_CATEGORIES))],
        ], ['devices.required' => 'Pilih minimal satu perangkat.']);

        $allowed = Device::hasAccess($request->user())->whereIn('device_id', $data['devices'])->pluck('device_id');
        $done = 0;
        $failed = [];

        foreach (Device::whereIn('device_id', $allowed)->get() as $device) {
            $detail = $this->sync->ensure($device);
            if ($data['action'] === 'assign') {
                $detail->update(array_filter([
                    'site_id' => $data['site_id'] ?? null,
                    'category' => $data['category'] ?? null,
                ]));
                $done++;
                continue;
            }

            try {
                if (blank($detail->category) && $detail->suggested_category && $request->boolean('accept_suggested')) {
                    $detail->update(['category' => $detail->suggested_category]);
                }
                $this->verification->verify($detail->fresh());
                $done++;
            } catch (ValidationException) {
                $failed[] = $device->displayName();
            }
        }

        $message = $data['action'] === 'assign' ? "{$done} perangkat diperbarui." : "{$done} perangkat diverifikasi.";
        $redirect = back()->with('antareja_success', $message);
        if ($failed) {
            $redirect->with('antareja_error', count($failed) . ' perangkat belum bisa diverifikasi (kategori/site/PIC belum diisi): '
                . implode(', ', array_slice($failed, 0, 10)) . (count($failed) > 10 ? ', …' : ''));
        }

        return $redirect;
    }

    public function sync(): RedirectResponse
    {
        $result = $this->sync->syncAll();

        return back()->with('antareja_success', "Sinkronisasi selesai: {$result['created']} perangkat baru masuk antrean.");
    }
}
