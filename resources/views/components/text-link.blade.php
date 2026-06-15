<a {{ $attributes->merge(['class' => 'font-medium text-zinc-900 underline decoration-zinc-300 underline-offset-4 transition hover:text-zinc-700 dark:text-zinc-100 dark:decoration-zinc-600 dark:hover:text-white']) }}>
    {{ $slot }}
</a>
