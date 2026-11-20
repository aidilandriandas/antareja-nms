<?php

namespace Antareja\Http\Controllers;

use Antareja\Services\AuditLogger;
use Antareja\Services\ConnectionTester;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Uji koneksi (ping + SNMP) dari web, dan aktifkan SNMP untuk perangkat yang terdaftar "ping saja". */
class DeviceTestController extends Controller
{
    /** Dari form Tambah Perangkat (perangkat belum terdaftar). */
    public function form(Request $request, ConnectionTester $tester): JsonResponse
    {
        Gate::authorize('device.create');

        return response()->json($this->strip($tester->test($this->params($request, true))));
    }

    /** Perangkat yang sudah terdaftar: isian kosong = pakai kredensial tersimpan (tidak dikirim ke browser). */
    public function device(Request $request, Device $device, ConnectionTester $tester): JsonResponse
    {
        Gate::authorize('view', $device);
        $result = $tester->test($this->paramsFor($request, $device));
        $result['ping_only'] = (bool) $device->snmp_disable;
        $result['can_enable'] = $device->snmp_disable && ($result['snmp']['ok'] ?? false) && $request->user()->can('device.update');

        return response()->json($this->strip($result));
    }

    public function enableSnmp(Request $request, Device $device, ConnectionTester $tester, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('view', $device);
        Gate::authorize('device.update');
        $result = $tester->test($this->paramsFor($request, $device));
        if (! ($result['snmp']['ok'] ?? false)) {
            return response()->json(['message' => 'SNMP belum terbaca — pengaturan tidak diubah.'] + $this->strip($result), 422);
        }

        $device->forceFill($result['snmp']['used'] + ['snmp_disable' => 0]);
        if ($device->os === 'ping') {
            $device->os = 'generic'; // OS sebenarnya dikenali ulang saat discovery
        }
        $device->save();
        $audit->log('updated', $device, "SNMP diaktifkan untuk {$device->displayName()} (sebelumnya ping saja)");

        // Discovery di latar belakang agar port, sensor, & OS langsung terdeteksi
        exec(sprintf('nohup %s %s device:discover %d > /dev/null 2>&1 &',
            escapeshellarg(PHP_BINARY && ! str_contains(PHP_BINARY, 'fpm') ? PHP_BINARY : 'php'), escapeshellarg(base_path('artisan')), $device->device_id));

        return response()->json(['ok' => true, 'message' => "SNMP aktif untuk {$device->displayName()}. Port, trafik, dan sensor sedang dideteksi — muncul dalam beberapa menit."]);
    }

    private function params(Request $request, bool $hostRequired): array
    {
        return $request->validate([
            'hostname' => [$hostRequired ? 'required' : 'nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\-:_]*$/'],
            'mode' => ['required', Rule::in(['v2c', 'v3', 'ping'])],
            'community' => ['nullable', 'string', 'max:128'],
            'v3_user' => ['nullable', 'string', 'max:64'],
            'v3_auth_pass' => ['nullable', 'string', 'max:128'],
            'v3_auth_algo' => ['nullable', Rule::in(['MD5', 'SHA', 'SHA-224', 'SHA-256', 'SHA-384', 'SHA-512'])],
            'v3_priv_pass' => ['nullable', 'string', 'max:128'],
            'v3_priv_algo' => ['nullable', Rule::in(['DES', 'AES', 'AES-192', 'AES-256', 'AES-256-C'])],
        ], ['hostname.required' => 'Isi alamat IP atau hostname dulu.', 'hostname.regex' => 'Alamat IP / hostname tidak valid.']);
    }

    private function paramsFor(Request $request, Device $device): array
    {
        $p = $this->params($request, false);
        $p['hostname'] = $device->overwrite_ip ?: $device->hostname;
        $p['port'] = $device->port ?: 161;
        if ($p['mode'] === 'v2c' && empty($p['community']) && $device->community) {
            $p['community'] = $device->community;
            $p['community_source'] = 'community yang tersimpan di perangkat';
        }
        if ($p['mode'] === 'v3' && empty($p['v3_user']) && $device->authname) {
            $p += ['v3_user' => $device->authname, 'v3_auth_pass' => $device->authpass, 'v3_auth_algo' => $device->authalgo,
                'v3_priv_pass' => $device->cryptopass, 'v3_priv_algo' => $device->cryptoalgo];
        }

        return $p;
    }

    /** Jangan pernah kirim community / password ke browser. */
    private function strip(array $r): array
    {
        unset($r['snmp']['used']);

        return $r;
    }
}
