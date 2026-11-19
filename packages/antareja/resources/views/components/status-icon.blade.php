{{-- Ikon status design system: bentuk berbeda per status agar terbaca tanpa warna. --}}
@props(['status' => 'unknown'])
@switch($status)
    @case('ok')
        <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="7" fill="currentColor"/><path d="M4.5 8.2l2.2 2.2 4.6-4.8" fill="none" stroke="var(--ant-surface-raised)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @break
    @case('warn')
        <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 1.2l7 13H1z" fill="currentColor"/><rect class="ant-icon-cut" x="7.1" y="5.4" width="1.8" height="4.6" rx=".9"/><circle class="ant-icon-cut" cx="8" cy="12" r="1"/></svg>
        @break
    @case('crit')
        <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M5.1 1h5.8L15 5.1v5.8L10.9 15H5.1L1 10.9V5.1z" fill="currentColor"/><rect class="ant-icon-cut" x="7.1" y="4" width="1.8" height="5.2" rx=".9"/><circle class="ant-icon-cut" cx="8" cy="11.4" r="1"/></svg>
        @break
    @case('down')
        <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M3.6 12.4l8.8-8.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @break
    @case('info')
        <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="7" fill="currentColor"/><rect class="ant-icon-cut" x="7.1" y="6.8" width="1.8" height="5" rx=".9"/><circle class="ant-icon-cut" cx="8" cy="4.6" r="1"/></svg>
        @break
    @default
        <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6.2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-dasharray="2.4 2"/></svg>
@endswitch
