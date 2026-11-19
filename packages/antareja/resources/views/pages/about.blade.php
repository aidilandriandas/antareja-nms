@extends('layouts.librenmsv1')

@section('title', 'Tentang ANTAREJA NMS')

@section('content')
<div class="container-fluid">
    <div class="tw:max-w-4xl">
        <h1 class="tw:text-2xl tw:font-bold tw:mt-2">Tentang ANTAREJA NMS</h1>
        <p class="tw:text-base">
            <strong>ANTAREJA NMS</strong> {{ $version }} — {{ config('antareja.full_name') }}.
        </p>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Dasar platform &amp; lisensi</strong></div>
            <div class="panel-body tw:text-sm tw:space-y-3">
                <p class="tw:m-0">
                    ANTAREJA NMS dibangun di atas <a href="https://www.librenms.org" target="_blank" rel="noopener">LibreNMS</a>
                    versi {{ $librenms_version }}, yang dilisensikan di bawah
                    <a href="https://www.gnu.org/licenses/gpl-3.0.html" target="_blank" rel="noopener">GNU General Public License v3</a>.
                    Sebagai karya turunan, ANTAREJA NMS juga didistribusikan di bawah <strong>GPLv3</strong>.
                    Seluruh catatan hak cipta LibreNMS dan kontributornya dipertahankan.
                </p>
                <p class="tw:m-0">
                    Fitur problem/trigger/SLA dan pengukuran kualitas jaringan di ANTAREJA terinspirasi dari konsep Zabbix dan SmokePing,
                    namun diimplementasikan ulang tanpa menyalin kode sumber keduanya.
                    Zabbix dan SmokePing adalah merek milik pemiliknya masing-masing.
                </p>
                <p class="tw:m-0">
                    Daftar perubahan terhadap kode inti LibreNMS dicatat di <code>MODIFICATIONS.md</code> pada repositori.
                </p>
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

        <p class="tw:text-sm"><a href="{{ url('about') }}">Informasi sistem LibreNMS &rarr;</a></p>
    </div>
</div>
@endsection
