@extends('layouts.librenmsv1')

@section('title', 'Tentang ANTAREJA NMS')

@section('content')
<div class="container-fluid">
    <div class="tw:max-w-6xl">
        <h1 class="tw:text-2xl tw:font-bold tw:mt-2">Tentang ANTAREJA NMS</h1>
        <p class="tw:text-base">
            <strong>ANTAREJA NMS</strong> {{ $version }} — {{ config('antareja.full_name') }}.
        </p>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Informasi sistem</strong></div>
            <div class="panel-body tw:p-0">
                <table class="table table-condensed tw:m-0">
                    @foreach($system as $label => $value)
                        <tr><th class="tw:w-56">{{ $label }}</th><td><code>{{ $value }}</code></td></tr>
                    @endforeach
                </table>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Role ANTAREJA</strong></div>
            <div class="panel-body tw:text-sm">
                <ul class="tw:m-0 tw:pl-5 tw:list-disc tw:space-y-1">
                    <li><strong>ICT Network</strong> (<code>ict-network</code>) — monitoring, inventaris jaringan, IPAM, mapping, incident jaringan.</li>
                    <li><strong>ICT CCTV / Support</strong> (<code>ict-cctv-support</code>) — CCTV, inventaris CCTV, incident support, maintenance.</li>
                    <li><strong>ICT Admin</strong> (<code>admin</code>) — seluruh akses, user, konfigurasi sistem, audit.</li>
                    <li><strong>Management</strong> (<code>management</code>) — dashboard eksekutif, laporan, SLA (baca saja).</li>
                </ul>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Lisensi &amp; hak cipta</strong></div>
            <div class="panel-body tw:text-sm tw:space-y-2">
                <p class="tw:m-0">
                    ANTAREJA NMS adalah perangkat lunak bebas di bawah
                    <a href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank" rel="noopener">GNU General Public License v3</a>,
                    disediakan <em>tanpa garansi apa pun</em>. Engine monitoring-nya berasal dari proyek open-source
                    LibreNMS {{ $librenms_version }} (&copy; para kontributor LibreNMS, GPLv3); catatan hak cipta aslinya dipertahankan
                    di kode sumber, dan perubahan pada kode inti dicatat di <code>MODIFICATIONS.md</code>.
                </p>
                <p class="tw:m-0 tw:text-gray-500">
                    Konsep problem/trigger/SLA dan pengukuran kualitas terinspirasi Zabbix dan SmokePing, diimplementasikan ulang
                    tanpa menyalin kode sumbernya. Nama-nama tersebut adalah merek milik pemiliknya masing-masing.
                </p>
            </div>
        </div>
        <div class="tw:mt-8">@include('antareja::partials.module-catalog')</div>
    </div>
</div>
@endsection
