{{-- Tabel mapping. $links: koleksi DeviceLink; $focus (opsional): device_id yang sedang dilihat --}}
@php($catalog = \Antareja\Support\Catalog::class)
<div class="table-responsive">
    <table class="table table-condensed table-hover tw:m-0">
        <thead>
        <tr><th>A (upstream)</th><th>Port A</th><th></th><th>B (downstream)</th><th>Port B</th><th>Tipe</th><th>Sumber / bukti</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        @forelse($links as $link)
            @php($st = $catalog::LINK_STATUS[$link->status])
            <tr class="{{ $link->status === 'rejected' ? 'tw:opacity-60' : '' }}">
                <td>
                    @if(($focus ?? null) === $link->a_device_id)<strong>{{ $link->aDevice?->displayName() }}</strong>
                    @else<a href="{{ route('antareja.inventory.show', $link->a_device_id) }}">{{ $link->aDevice?->displayName() }}</a>@endif
                </td>
                <td class="tw:text-xs">{{ $link->aPort?->getShortLabel() ?? '—' }}</td>
                <td><i class="fa fa-long-arrow-alt-right tw:text-gray-400"></i></td>
                <td>
                    @if(($focus ?? null) === $link->b_device_id)<strong>{{ $link->bDevice?->displayName() }}</strong>
                    @else<a href="{{ route('antareja.inventory.show', $link->b_device_id) }}">{{ $link->bDevice?->displayName() }}</a>@endif
                </td>
                <td class="tw:text-xs">{{ $link->bPort?->getShortLabel() ?? '—' }}</td>
                <td class="tw:text-xs">{{ $link->typeLabel() }}</td>
                <td class="tw:text-xs">
                    <span class="label label-default">{{ $catalog::label($catalog::LINK_SOURCES, $link->source) }}</span>
                    @if($link->evidence_text)<div class="tw:text-gray-500 tw:mt-1">{{ $link->evidence_text }}</div>@endif
                    @if($link->notes)<div class="tw:mt-1">{{ $link->notes }}</div>@endif
                </td>
                <td>
                    <span class="label {{ $st['class'] }}">{{ $st['label'] }}</span>
                    @if($link->verified_at && $link->status !== 'suggested')
                        <div class="tw:text-xs tw:text-gray-500">{{ $link->verified_at->format('d M Y') }}@if($link->verifier) · {{ $link->verifier->username }}@endif</div>
                    @endif
                </td>
                <td class="tw:whitespace-nowrap">
                    @can('ant-mapping.verify')
                        @if($link->status !== 'verified')
                            <form method="post" action="{{ route('antareja.mapping.verify', $link) }}" class="tw:inline">@csrf
                                <button class="btn btn-xs btn-success" title="Verifikasi"><i class="fa fa-check"></i></button>
                            </form>
                        @endif
                        @if($link->status !== 'rejected')
                            <form method="post" action="{{ route('antareja.mapping.reject', $link) }}" class="tw:inline">@csrf
                                <button class="btn btn-xs btn-default" title="Tolak"><i class="fa fa-ban"></i></button>
                            </form>
                        @endif
                    @endcan
                    @can('ant-mapping.update')
                        <form method="post" action="{{ route('antareja.mapping.destroy', $link) }}" class="tw:inline" onsubmit="return confirm('Hapus mapping ini?')">@csrf @method('DELETE')
                            <button class="btn btn-xs btn-default" title="Hapus"><i class="fa fa-trash"></i></button>
                        </form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="tw:text-center tw:py-4 tw:text-gray-500">Belum ada mapping.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
