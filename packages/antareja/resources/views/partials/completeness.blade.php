{{-- Skor kelengkapan data → ProgressBar terbalik (tinggi = baik). --}}
<span title="{{ $completeness['missing'] ? 'Belum diisi: ' . implode(', ', $completeness['missing']) : 'Lengkap' }}">
    <x-antareja::progress :value="$completeness['score']" inverted :warn-at="100" :crit-at="60" label="Kelengkapan data" />
</span>
