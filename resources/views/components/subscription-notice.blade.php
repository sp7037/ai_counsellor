@props([
    'variant' => 'warning',
])

@php
    $styles = match ($variant) {
        'danger' => 'border-red-800/70 bg-red-950/80 text-red-100',
        'info' => 'border-sky-800/70 bg-sky-950/80 text-sky-100',
        default => 'border-amber-700/70 bg-amber-950/80 text-amber-100',
    };
@endphp

<div {{ $attributes->merge(['class' => "rounded-lg border px-4 py-3 text-sm {$styles}"]) }}>
    {{ $slot }}
</div>
