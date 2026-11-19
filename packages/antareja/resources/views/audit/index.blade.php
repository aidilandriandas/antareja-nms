@extends('antareja::layouts.page')

@section('title', 'Audit Log')
@section('breadcrumb')<li class="active">Audit Log</li>@endsection
@section('heading', 'Audit Log')
@section('subheading', 'Jejak login, perubahan inventaris, mapping, site, verifikasi, dan evidence.')

@section('page')
    <form method="get" class="form-inline tw:mb-3 tw:flex tw:flex-wrap tw:gap-2">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari deskripsi">
        <input type="text" name="user" value="{{ request('user') }}" class="form-control" placeholder="User">
        <select name="event" class="form-control">
            <option value="">Semua event</option>
            @foreach($events as $key => $label)<option value="{{ $key }}" @selected(request('event') === $key)>{{ $label }}</option>@endforeach
        </select>
        <select name="type" class="form-control">
            <option value="">Semua objek</option>
            @foreach($types as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="form-control" title="Dari tanggal">
        <input type="date" name="to" value="{{ request('to') }}" class="form-control" title="Sampai tanggal">
        <button class="btn btn-default"><i class="fa fa-filter"></i> Filter</button>
    </form>

    <div class="table-responsive">
        <table class="table table-condensed table-hover">
            <thead><tr><th class="tw:w-40">Waktu</th><th>User</th><th>Event</th><th>Deskripsi</th><th>Perubahan</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="tw:whitespace-nowrap tw:text-xs">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                    <td>{{ $log->username ?? 'system' }}</td>
                    <td><span class="label {{ in_array($log->event, ['deleted', 'rejected', 'login_failed']) ? 'label-danger' : (in_array($log->event, ['verified', 'login']) ? 'label-success' : 'label-default') }}">{{ $events[$log->event] ?? $log->event }}</span></td>
                    <td class="tw:text-sm">
                        @if($log->auditable_type === 'device' && $log->auditable_id)
                            <a href="{{ route('antareja.inventory.show', $log->auditable_id) }}">{{ $log->description }}</a>
                        @elseif($log->auditable_type === 'site' && $log->auditable_id && $log->event !== 'deleted')
                            <a href="{{ route('antareja.sites.show', $log->auditable_id) }}">{{ $log->description }}</a>
                        @else
                            {{ $log->description }}
                        @endif
                    </td>
                    <td class="tw:text-xs">
                        @if($log->old_values || $log->new_values)
                            <details>
                                <summary class="tw:cursor-pointer">{{ count($log->new_values ?? $log->old_values ?? []) }} field</summary>
                                <table class="table table-condensed tw:m-0 tw:mt-1">
                                    @foreach(array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))) as $field)
                                        <tr>
                                            <td class="tw:font-mono">{{ $field }}</td>
                                            <td class="ant-text-crit">{{ is_scalar($log->old_values[$field] ?? null) ? \Illuminate\Support\Str::limit((string) $log->old_values[$field], 60) : json_encode($log->old_values[$field] ?? null) }}</td>
                                            <td class="ant-text-ok">{{ is_scalar($log->new_values[$field] ?? null) ? \Illuminate\Support\Str::limit((string) $log->new_values[$field], 60) : json_encode($log->new_values[$field] ?? null) }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </details>
                        @endif
                    </td>
                    <td class="tw:text-xs">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="tw:text-center tw:py-6 ant-muted">Belum ada catatan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
@endsection
