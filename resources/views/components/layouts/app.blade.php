<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-neutral-950">
        <div class="mx-auto flex min-h-screen max-w-5xl flex-col gap-6 p-6">
            <header class="flex items-center justify-between border-b border-zinc-800 pb-4">
                <div>
                    <x-app-logo :href="route('home')" size="header" />
                    @isset($heading)
                        <p class="mt-1 text-sm text-zinc-400">{{ $heading }}</p>
                    @endisset
                </div>
                <div class="flex items-center gap-3 text-sm text-zinc-300">
                    {{ $actions ?? '' }}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:button type="submit" variant="ghost" size="sm">Log out</flux:button>
                    </form>
                </div>
            </header>
            <main class="flex-1">
                {{ $slot }}
            </main>
        </div>
        @fluxScripts
    </body>
</html>
