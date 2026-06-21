<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="dark min-h-screen bg-neutral-950 text-zinc-100 antialiased">
        <div class="flex min-h-screen">
            <aside class="hidden w-64 shrink-0 border-r border-zinc-800 bg-zinc-950 p-4 lg:block">
                @include('components.workspace.sidebar', ['tenant' => $tenant ?? request()->route('tenant')])
            </aside>
            <div class="flex min-w-0 flex-1 flex-col">
                <header class="flex items-center justify-between border-b border-zinc-800 px-4 py-3 lg:px-6">
                    <div class="flex items-center gap-3">
                        <flux:button class="lg:hidden" variant="ghost" size="sm" x-data x-on:click="document.getElementById('workspace-mobile-nav')?.classList.toggle('hidden')">Menu</flux:button>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-zinc-500">Counsellor Workspace</p>
                            @isset($heading)<h1 class="text-lg font-semibold text-white">{{ $heading }}</h1>@endisset
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-zinc-300">
                        <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">@csrf<flux:button type="submit" variant="ghost" size="sm">Log out</flux:button></form>
                    </div>
                </header>
                <div id="workspace-mobile-nav" class="border-b border-zinc-800 p-4 lg:hidden hidden">@include('components.workspace.sidebar', ['tenant' => $tenant ?? request()->route('tenant')])</div>
                <main class="flex-1 p-4 lg:p-6">{{ $slot }}</main>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
