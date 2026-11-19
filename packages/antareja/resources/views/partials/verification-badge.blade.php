@php($meta = \Antareja\Support\Catalog::VERIFICATION_DISPLAY[$status] ?? ['label' => $status, 'class' => 'label-default'])
<x-antareja::status :status="$meta['tone'] ?? 'unknown'" size="sm">{{ $meta['label'] }}</x-antareja::status>
