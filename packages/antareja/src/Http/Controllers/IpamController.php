<?php

namespace Antareja\Http\Controllers;

use Antareja\Models\IpAddress;
use Antareja\Models\Site;
use Antareja\Models\Subnet;
use Antareja\Models\Vlan;
use Antareja\Services\IpamService;
use Antareja\Support\IpMath;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IpamController extends Controller
{
    public function __construct(private readonly IpamService $ipam)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        // Pencarian satu alamat IP → langsung ke record / subnetnya
        if ($request->filled('q') && ($bin = IpMath::toBinary($request->input('q')))) {
            if ($ip = IpAddress::firstWhere('address_text', inet_ntop($bin))) {
                return redirect()->route('antareja.ipam.addresses.edit', $ip);
            }
            if ($subnet = $this->ipam->subnetFor($bin)) {
                return redirect()->route('antareja.ipam.subnets.show', $subnet)
                    ->with('antareja_success', inet_ntop($bin) . ' belum tercatat (tersedia) di subnet ini.');
            }

            return redirect()->route('antareja.ipam.index')->with('antareja_error', inet_ntop($bin) . ' tidak berada di subnet IPAM mana pun.');
        }

        $subnets = Subnet::with(['site', 'vlan'])
            ->when($request->filled('site'), fn ($q) => $q->where('site_id', (int) $request->input('site')))
            ->when($request->filled('purpose'), fn ($q) => $q->where('purpose', $request->input('purpose')))
            ->when($request->filled('vlan'), fn ($q) => $q->where('vlan_id', (int) $request->input('vlan')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%' . $request->input('q') . '%')
                ->orWhere('cidr', 'like', '%' . $request->input('q') . '%')))
            ->orderBy('family')->orderBy('network')->get();

        $stats = $subnets->mapWithKeys(fn (Subnet $s) => [$s->id => $this->ipam->stats($s)]);
        $v4 = $subnets->where('family', 4)->pluck('id');

        return view('antareja::ipam.index', [
            'subnets' => $subnets,
            'stats' => $stats,
            'totals' => [
                'subnets' => $subnets->count(),
                'usable' => $v4->sum(fn ($id) => $stats[$id]['usable']),
                'used' => $v4->sum(fn ($id) => $stats[$id]['used'] + $stats[$id]['static'] + $stats[$id]['dhcp_records']),
                'reserved' => $v4->sum(fn ($id) => $stats[$id]['reserved']),
                'dhcp_pool' => $v4->sum(fn ($id) => $stats[$id]['dhcp_pool']),
                'available' => $v4->sum(fn ($id) => $stats[$id]['available']),
                'allocated' => $v4->sum(fn ($id) => $stats[$id]['allocated']),
            ],
            'conflicts' => IpAddress::where('conflict', true)->count(),
            'orphans' => IpAddress::whereNull('subnet_id')->count(),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
            'vlans' => Vlan::with('site')->orderBy('vlan_number')->get(),
            'candidates' => $this->ipam->importCandidates()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('antareja::ipam.subnet-form', $this->formData(new Subnet([
            'cidr' => $request->input('cidr'), 'is_active' => true, 'purpose' => 'management',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $subnet = $this->ipam->createSubnet($this->validatedSubnet($request));

        return redirect()->route('antareja.ipam.subnets.show', $subnet)->with('antareja_success', "Subnet {$subnet->cidr} ditambahkan.");
    }

    public function show(Request $request, Subnet $subnet): View
    {
        $subnet->load(['site', 'vlan']);
        $addresses = IpAddress::with(['device', 'port'])->where('subnet_id', $subnet->id)
            ->when($request->filled('status'), fn ($q) => $request->input('status') === 'conflict'
                ? $q->where('conflict', true) : $q->where('status', $request->input('status')))
            ->orderBy('address')->paginate(256)->withQueryString();

        return view('antareja::ipam.subnet-show', [
            'subnet' => $subnet,
            'stats' => $this->ipam->stats($subnet),
            'addresses' => $addresses,
            'map' => $this->ipam->addressMap($subnet),
            'free' => $this->ipam->freeAddresses($subnet, 20),
            'devices' => Device::hasAccess($request->user())->orderBy('hostname')->get(),
        ]);
    }

    public function edit(Subnet $subnet): View
    {
        return view('antareja::ipam.subnet-form', $this->formData($subnet));
    }

    public function update(Request $request, Subnet $subnet): RedirectResponse
    {
        $data = $this->validatedSubnet($request, $subnet);
        unset($data['cidr']); // CIDR tidak diubah; hapus & buat ulang bila perlu
        $subnet->update($data);
        $this->ipam->reassignSubnets();

        return redirect()->route('antareja.ipam.subnets.show', $subnet)->with('antareja_success', "Subnet {$subnet->cidr} disimpan.");
    }

    public function destroy(Subnet $subnet): RedirectResponse
    {
        $cidr = $subnet->cidr;
        $subnet->delete();
        $this->ipam->reassignSubnets();

        return redirect()->route('antareja.ipam.index')->with('antareja_success', "Subnet {$cidr} dihapus. Record IP tetap disimpan dan dipindah ke subnet induk bila ada.");
    }

    public function allocate(Request $request, Subnet $subnet): RedirectResponse
    {
        $data = $this->validatedAddress($request);
        $address = $request->input('address');

        if ($request->input('mode') === 'next' || blank($address)) {
            $address = $this->ipam->freeAddresses($subnet, 1)[0] ?? null;
            if (! $address) {
                return back()->with('antareja_error', "Tidak ada IP kosong di {$subnet->cidr}.");
            }
        }

        $bin = IpMath::toBinary((string) $address);
        if (! $bin || ! $subnet->containsBinary($bin)) {
            throw ValidationException::withMessages(['address' => "Alamat {$address} tidak berada di {$subnet->cidr}."]);
        }
        if (IpAddress::where('address_text', inet_ntop($bin))->exists()) {
            throw ValidationException::withMessages(['address' => inet_ntop($bin) . ' sudah tercatat. Edit record yang ada.']);
        }

        $ip = $this->ipam->allocate($address, $data);

        return back()->with('antareja_success', "IP {$ip->address_text} dialokasikan ({$ip->status}).");
    }

    public function editAddress(IpAddress $ip): View
    {
        return view('antareja::ipam.address-edit', [
            'ip' => $ip->load(['subnet', 'device', 'port', 'history.user', 'assigner']),
            'devices' => Device::hasAccess(request()->user())->orderBy('hostname')->get(),
        ]);
    }

    public function updateAddress(Request $request, IpAddress $ip): RedirectResponse
    {
        $this->ipam->allocate($ip->address_text, $this->validatedAddress($request));

        return redirect()->route('antareja.ipam.addresses.edit', $ip)->with('antareja_success', "IP {$ip->address_text} disimpan.");
    }

    public function releaseAddress(IpAddress $ip): RedirectResponse
    {
        $subnet = $ip->subnet;
        $text = $ip->address_text;
        $this->ipam->release($ip);

        return ($subnet ? redirect()->route('antareja.ipam.subnets.show', $subnet) : redirect()->route('antareja.ipam.index'))
            ->with('antareja_success', "IP {$text} dilepas. Bila masih aktif di jaringan, IP akan tercatat kembali saat sinkronisasi.");
    }

    public function refreshArp(): RedirectResponse
    {
        if (! \Illuminate\Support\Facades\Cache::has('antareja:arp-refresh:running')) {
            \Illuminate\Support\Facades\Cache::put('antareja:arp-refresh:running', now()->toIso8601String(), 900);
            exec(sprintf('nohup %s %s antareja:arp-refresh > /dev/null 2>&1 &',
                escapeshellarg(PHP_BINARY && ! str_contains(PHP_BINARY, 'fpm') ? PHP_BINARY : 'php'), escapeshellarg(base_path('artisan'))));
        }

        return back()->with('antareja_success', 'Tabel ARP sedang dibaca ulang dari router/L3. Muat ulang halaman ini dalam 1–2 menit; konflik yang sudah tidak ada akan hilang sendiri.');
    }

    public function conflicts(): View
    {
        return view('antareja::ipam.conflicts', [
            'arpLast' => \Illuminate\Support\Facades\Cache::get('antareja:arp-refresh:last'),
            'overlaps' => $this->ipam->interfaceOverlaps(),
            'arpRunning' => \Illuminate\Support\Facades\Cache::has('antareja:arp-refresh:running'),
            'conflicts' => IpAddress::with(['subnet', 'device'])->where('conflict', true)->orderBy('address')->get(),
            'orphans' => IpAddress::with('device')->whereNull('subnet_id')->orderBy('address')->limit(500)->get(),
        ]);
    }

    public function importForm(): View
    {
        return view('antareja::ipam.import', [
            'candidates' => $this->ipam->importCandidates(),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cidrs' => ['required', 'array', 'max:500'],
            'cidrs.*' => ['string', 'max:49'],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'purpose' => ['nullable', Rule::in(array_keys(Subnet::PURPOSES))],
        ], ['cidrs.required' => 'Pilih minimal satu network.']);

        $created = 0;
        foreach ($data['cidrs'] as $cidr) {
            try {
                if (! Subnet::where('cidr', IpMath::parseCidr($cidr)['cidr'])->exists()) {
                    $this->ipam->createSubnet(['cidr' => $cidr, 'name' => $cidr, 'site_id' => $data['site_id'] ?? null, 'purpose' => $data['purpose'] ?? null, 'is_active' => true]);
                    $created++;
                }
            } catch (\InvalidArgumentException) {
                // lewati CIDR tidak valid
            }
        }
        $this->ipam->sync();

        return redirect()->route('antareja.ipam.index')->with('antareja_success', "{$created} subnet diimpor dan IP terpakai disinkronkan.");
    }

    public function sync(): RedirectResponse
    {
        $r = $this->ipam->sync();

        return back()->with('antareja_success', "Sinkronisasi IPAM: {$r['created']} IP baru, {$r['updated']} diperbarui, {$r['conflicts']} konflik.");
    }

    public function vlans(): View
    {
        return view('antareja::ipam.vlans', [
            'vlans' => Vlan::with('site')->withCount('subnets')->orderBy('vlan_number')->get(),
            'discovered' => \Illuminate\Support\Facades\DB::table('vlans')->join('devices', 'devices.device_id', '=', 'vlans.device_id')
                ->selectRaw('vlan_vlan, MAX(vlan_name) AS vlan_name, COUNT(DISTINCT vlans.device_id) AS devices')
                ->where('vlan_vlan', '>', 0)->groupBy('vlan_vlan')->orderBy('vlan_vlan')->get(),
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function storeVlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vlan_number' => ['required', 'integer', 'between:1,4094',
                Rule::unique('ant_vlans')->where(fn ($q) => $request->filled('site_id') ? $q->where('site_id', $request->input('site_id')) : $q->whereNull('site_id'))],
            'name' => ['required', 'string', 'max:64'],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'purpose' => ['nullable', Rule::in(array_keys(Subnet::PURPOSES))],
            'description' => ['nullable', 'string', 'max:1000'],
        ], ['vlan_number.unique' => 'VLAN ini sudah terdaftar untuk site tersebut.'], ['vlan_number' => 'nomor VLAN', 'name' => 'nama VLAN']);
        $vlan = Vlan::create($data);

        return back()->with('antareja_success', "VLAN {$vlan->vlan_number} ditambahkan.");
    }

    public function destroyVlan(Vlan $vlan): RedirectResponse
    {
        $vlan->delete();

        return back()->with('antareja_success', "VLAN {$vlan->vlan_number} dihapus.");
    }

    private function validatedSubnet(Request $request, ?Subnet $subnet = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:128'],
            'purpose' => ['nullable', Rule::in(array_keys(Subnet::PURPOSES))],
            'site_id' => ['nullable', 'integer', 'exists:ant_sites,id'],
            'vlan_id' => ['nullable', 'integer', 'exists:ant_vlans,id'],
            'gateway' => ['nullable', 'ip'],
            'dhcp_start' => ['nullable', 'ip', 'required_with:dhcp_end'],
            'dhcp_end' => ['nullable', 'ip', 'required_with:dhcp_start'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
        if (! $subnet) {
            $rules['cidr'] = ['required', 'string', 'max:49'];
        }
        $data = $request->validate($rules, [], ['cidr' => 'CIDR', 'name' => 'nama subnet', 'dhcp_start' => 'awal DHCP', 'dhcp_end' => 'akhir DHCP']);

        if (! $subnet) {
            try {
                $parsed = IpMath::parseCidr($data['cidr']);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['cidr' => $e->getMessage()]);
            }
            if (Subnet::where('cidr', $parsed['cidr'])->exists()) {
                throw ValidationException::withMessages(['cidr' => "Subnet {$parsed['cidr']} sudah ada."]);
            }
            $network = $parsed['network'];
            $prefix = $parsed['prefix'];
        } else {
            [$network, $prefix] = [$subnet->network, $subnet->prefix];
        }

        foreach (['gateway', 'dhcp_start', 'dhcp_end'] as $field) {
            if (filled($data[$field] ?? null)) {
                $bin = IpMath::toBinary($data[$field]);
                if (! IpMath::contains($network, $prefix, $bin)) {
                    throw ValidationException::withMessages([$field => "{$data[$field]} tidak berada di subnet ini."]);
                }
                $data[$field] = $bin;
            } else {
                $data[$field] = null;
            }
        }
        if ($data['dhcp_start'] && $data['dhcp_end'] && strcmp($data['dhcp_start'], $data['dhcp_end']) > 0) {
            throw ValidationException::withMessages(['dhcp_end' => 'Akhir pool DHCP harus setelah awal pool.']);
        }
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function validatedAddress(Request $request): array
    {
        $data = $request->validate([
            'address' => ['nullable', 'ip'],
            'status' => ['required', Rule::in(array_keys(IpAddress::STATUSES))],
            'hostname' => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:255'],
            'mac_address' => ['nullable', 'string', 'max:32', 'regex:/^([0-9A-Fa-f]{2}[:\-.]?){5}[0-9A-Fa-f]{2}$/'],
            'device_id' => ['nullable', 'integer', 'exists:devices,device_id'],
        ], ['mac_address.regex' => 'Format MAC tidak valid.'], ['address' => 'alamat IP']);
        $data['mac_address'] = IpMath::normalizeMac($data['mac_address'] ?? null);

        return $data;
    }

    private function formData(Subnet $subnet): array
    {
        return [
            'subnet' => $subnet,
            'sites' => Site::orderBy('code')->get(['id', 'code', 'name']),
            'vlans' => Vlan::with('site')->orderBy('vlan_number')->get(),
            'purposes' => Subnet::PURPOSES,
        ];
    }
}
