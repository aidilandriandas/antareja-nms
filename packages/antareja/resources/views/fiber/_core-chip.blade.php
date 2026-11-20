{{-- Nomor core dengan warna standar TIA-598 (warna + nomor + nama warna, tidak bergantung warna saja) --}}
<span class="ant-core-chip" title="{{ $core->colorLabel() }}"><span class="ant-core-chip__dot" style="background: {{ $core->colorHex() }}"></span>{{ $core->core_no }}</span>
