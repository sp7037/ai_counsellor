@props([
    'label',
    'value',
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-zinc-800 bg-zinc-900 p-4']) }}>
    <p class="text-xs uppercase tracking-wide text-zinc-500">{{ $label }}</p>
    <p class="mt-2 text-2xl font-semibold text-white">{{ $value }}</p>
</div>
