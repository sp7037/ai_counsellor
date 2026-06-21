@props([
    'size' => 'sidebar',
    'showName' => false,
    'href' => null,
])

@php
    $sizes = match ($size) {
        'auth' => 'h-14 w-auto max-w-[220px]',
        'header' => 'h-8 w-auto max-w-[160px]',
        default => 'h-10 w-auto max-w-[180px]',
    };

    $logoUrl = asset(\App\Support\Branding::logoPath());
    $home = $href ?? route('home');
@endphp

<a href="{{ $home }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-3', 'wire:navigate' => true]) }}>
    <img
        src="{{ $logoUrl }}"
        alt="AI Counsellor"
        class="{{ $sizes }} object-contain"
    />
    @if ($showName)
        <span class="text-sm font-semibold text-white">{{ config('app.name') }}</span>
    @endif
</a>
