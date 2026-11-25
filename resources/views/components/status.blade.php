@props(['value'])
@php
    $label = is_object($value) && method_exists($value, 'label') ? $value->label() : (string) $value;
    $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $label), '-'));
@endphp
<span {{ $attributes->merge(['class' => 'badge badge-'.$slug]) }}>{{ $label }}</span>
