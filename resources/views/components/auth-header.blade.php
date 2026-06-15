@props(['title', 'description' => null])

<div class="flex flex-col gap-2 text-center">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    @if ($description)
        <flux:subheading>{{ $description }}</flux:subheading>
    @endif
</div>
