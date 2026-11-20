@php($m = ['up' => ['ok', 'Up'], 'degraded' => ['warn', 'Degradasi'], 'down' => ['down', 'Down'], 'maintenance' => ['info', 'Maintenance'], 'empty' => ['unknown', 'Tanpa anggota']][$state] ?? ['unknown', $state])
<x-antareja::status :status="$m[0]" size="sm">{{ $m[1] }}</x-antareja::status>
