@php($meta = \Antareja\Support\Catalog::VERIFICATION_DISPLAY[$status] ?? ['label' => $status, 'class' => 'label-default'])
<span class="label {{ $meta['class'] }}">{{ $meta['label'] }}</span>
