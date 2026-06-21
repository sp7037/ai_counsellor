@props([
    'heading' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-zinc-800 bg-zinc-900 p-5 text-zinc-100 shadow-sm']) }}>
    @if ($heading)
        <h2 class="mb-4 text-base font-semibold text-white">{{ $heading }}</h2>
    @endif
    {{ $slot }}
</div>
