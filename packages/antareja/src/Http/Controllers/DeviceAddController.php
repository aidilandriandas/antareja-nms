<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\Site;
use Antareja\Services\DeviceOnboarding;
use Antareja\Support\Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Tambah perangkat ala ANTAREJA: nama, grup (kategori), site, dan cara pemantauan dalam satu form; bisa banyak sekaligus. */
class DeviceAddController extends Controller
{
    public const BULK_MAX = 50;

    public function create(Request $request): View
    {
        Gate::authorize('device.create');

        return view('antareja::devices.add', [
            'categories' => Catalog::GROUP_NAMES,
            'sites' => Site::with('areas')->where('is_active', true)->orderBy('code')->get(),
            'results' => session('antareja_add_results'),
            'mode' => $request->input('tab', session('antareja_add_tab', 'single')),
        ]);
    }

    public function store(Request $request, DeviceOnboarding $onboarding): RedirectResponse
    {
        Gate::authorize('device.create');
        $bulk = $request->input('tab') === 'bulk';
        $data = $request->validate($this->rules($bulk), [
            'hostname.regex' => 'Isi alamat IP atau nama host yang valid (tanpa http:// dan tanpa spasi).',
            'lines.required' => 'Isi minimal satu baris perangkat.',
            'hostname.required' => 'Isi alamat IP atau hostname perangkat.',
            'v3_user.required_if' => 'Isi username SNMP v3.',
            'v3_auth_pass.min' => 'Password SNMP v3 minimal 8 karakter.',
            'v3_priv_pass.min' => 'Password SNMP v3 minimal 8 karakter.',
        ], ['hostname' => 'IP / hostname', 'name' => 'nama perangkat', 'category' => 'grup', 'site_id' => 'site', 'lines' => 'daftar perangkat']);

        $common = [
            'category' => $data['category'] ?? null,
            'site_id' => $data['site_id'] ?? null,
            'area_id' => $data['area_id'] ?? null,
            'mode' => $data['mode'],
            'community' => $data['community'] ?? null,
            'v3_user' => $data['v3_user'] ?? null,
            'v3_auth_pass' => $data['v3_auth_pass'] ?? null,
            'v3_auth_algo' => $data['v3_auth_algo'] ?? 'SHA',
            'v3_priv_pass' => $data['v3_priv_pass'] ?? null,
            'v3_priv_algo' => $data['v3_priv_algo'] ?? 'AES',
            'force' => $request->boolean('force'),
        ];

        if (! $bulk) {
            $result = $onboarding->add(['hostname' => $data['hostname'], 'name' => $data['name']] + $common);
            if (! $result['ok']) {
                return back()->withInput($request->except(['v3_auth_pass', 'v3_priv_pass', 'community']))
                    ->withErrors(['hostname' => $result['message']]);
            }

            return redirect()->route('antareja.inventory.show', $result['device'])
                ->with('antareja_success', "{$result['device']->displayName()} ({$data['hostname']}) ditambahkan ke monitoring. {$result['message']}. Data pertama muncul setelah polling berikutnya (± 5 menit).");
        }

        [$rows, $errors] = $this->parseLines($data['lines']);
        if ($errors) {
            return back()->withInput($request->except(['v3_auth_pass', 'v3_priv_pass', 'community']))->withErrors(['lines' => $errors])->with('antareja_add_tab', 'bulk');
        }
        @set_time_limit(60 + 20 * count($rows));
        $results = [];
        $failedLines = [];
        foreach ($rows as $row) {
            $r = $onboarding->add(['hostname' => $row['hostname'], 'name' => $row['name'], 'category' => $row['category'] ?? $common['category']] + $common);
            $results[] = ['hostname' => $row['hostname'], 'name' => $row['name'], 'ok' => $r['ok'], 'message' => $r['message'],
                'url' => $r['device'] ? route('antareja.inventory.show', $r['device']) : null];
            if (! $r['ok']) {
                $failedLines[] = implode(', ', array_filter([$row['hostname'], $row['name'], isset($row['category']) ? (Catalog::GROUP_NAMES[$row['category']] ?? null) : null]));
            }
        }
        $ok = collect($results)->where('ok', true)->count();

        return redirect()->route('antareja.devices.add', ['tab' => 'bulk'])
            // Sisakan hanya baris yang gagal agar bisa diperbaiki & dikirim ulang tanpa duplikat
            ->withInput($failedLines ? ['lines' => implode("\n", $failedLines)] + $request->except(['v3_auth_pass', 'v3_priv_pass', 'community', 'lines']) : [])
            ->with('antareja_add_results', $results)
            ->with($ok ? 'antareja_success' : 'antareja_warning', "$ok dari " . count($results) . ' perangkat berhasil ditambahkan.');
    }

    private function rules(bool $bulk): array
    {
        $host = ['string', 'max:128', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\-:_]*$/'];

        return [
            'hostname' => $bulk ? ['nullable'] : array_merge(['required'], $host),
            'name' => ['nullable', 'string', 'max:128'],
            'lines' => $bulk ? ['required', 'string', 'max:20000'] : ['nullable'],
            'category' => ['nullable', Rule::in(array_keys(Catalog::DEVICE_CATEGORIES))],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'area_id' => ['nullable', 'integer', 'exists:ant_areas,id'],
            'mode' => ['required', Rule::in(['v2c', 'v3', 'ping'])],
            'community' => ['nullable', 'string', 'max:128'],
            'v3_user' => ['nullable', 'required_if:mode,v3', 'string', 'max:64'],
            'v3_auth_pass' => ['nullable', 'string', 'min:8', 'max:128'],
            'v3_auth_algo' => ['nullable', Rule::in(['MD5', 'SHA', 'SHA-224', 'SHA-256', 'SHA-384', 'SHA-512'])],
            'v3_priv_pass' => ['nullable', 'string', 'min:8', 'max:128'],
            'v3_priv_algo' => ['nullable', Rule::in(['DES', 'AES', 'AES-192', 'AES-256', 'AES-256-C'])],
        ];
    }

    /**
     * Satu perangkat per baris: "IP, Nama" atau "IP, Nama, grup" (pemisah koma / titik koma / tab).
     *
     * @return array{0: list<array{hostname: string, name: ?string, category?: string}>, 1: list<string>}
     */
    private function parseLines(string $text): array
    {
        $rows = [];
        $errors = [];
        $groupKeys = array_change_key_case(array_flip(array_map('strtolower', Catalog::GROUP_NAMES)));
        foreach (preg_split('/\R/', $text) as $i => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', preg_split('/[,;\t]/', $line));
            $host = $parts[0];
            if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9.\-:_]*$/', $host) || strlen($host) > 128) {
                $errors[] = 'Baris ' . ($i + 1) . ": \"$host\" bukan IP / hostname yang valid.";
                continue;
            }
            $row = ['hostname' => $host, 'name' => ($parts[1] ?? '') !== '' ? mb_substr($parts[1], 0, 128) : null];
            if (($parts[2] ?? '') !== '') {
                $g = strtolower($parts[2]);
                $key = isset(Catalog::DEVICE_CATEGORIES[$g]) ? $g : ($groupKeys[$g] ?? null);
                if ($key === null) {
                    $errors[] = 'Baris ' . ($i + 1) . ": grup \"{$parts[2]}\" tidak dikenal.";
                    continue;
                }
                $row['category'] = $key;
            }
            $rows[] = $row;
        }
        if (! $rows && ! $errors) {
            $errors[] = 'Isi minimal satu baris perangkat.';
        }
        if (count($rows) > self::BULK_MAX) {
            $errors[] = 'Maksimal ' . self::BULK_MAX . ' perangkat per kali simpan (ada ' . count($rows) . ').';
        }

        return [$rows, $errors];
    }
}
