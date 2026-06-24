<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'AI Counsellor') }} — AI Counsellor for Education, Healthcare & Service Organisations</title>
        <meta name="description" content="Automate student and visitor enquiries, qualify leads, and hand over to human counsellors when needed. Multi-tenant AI counsellor platform by SR Worlds." />

        <link rel="icon" type="image/png" href="{{ asset(\App\Support\Branding::faviconPath()) }}" />
        <link rel="apple-touch-icon" href="{{ asset(\App\Support\Branding::logoPath()) }}" />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css'])
        @endif

        <style>
            :root { color-scheme: dark; }
            body { font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; }
            .lp-grid-bg {
                background-image:
                    radial-gradient(circle at 20% 0%, rgba(99, 102, 241, 0.18), transparent 45%),
                    radial-gradient(circle at 85% 10%, rgba(16, 185, 129, 0.12), transparent 40%);
            }
        </style>
    </head>
    <body class="min-h-screen bg-[#0a0a0b] text-zinc-200 antialiased">
        @php
            $loggedInUrl = auth()->check()
                ? app(\App\Services\Auth\PostLoginRedirect::class)->intendedUrl(auth()->user())
                : null;
            $demoUrl = url('/widget-demo/static.html');
        @endphp

        {{-- Navbar --}}
        <header class="sticky top-0 z-30 border-b border-white/5 bg-[#0a0a0b]/80 backdrop-blur">
            <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3">
                    <img src="{{ asset(\App\Support\Branding::logoPath()) }}" alt="{{ config('app.name') }}" class="h-9 w-auto max-w-[160px] object-contain" />
                    <span class="hidden text-sm font-semibold text-white sm:block">{{ config('app.name', 'AI Counsellor') }}</span>
                </a>

                <div class="flex items-center gap-3">
                    <a href="#features" class="hidden text-sm text-zinc-400 transition hover:text-white md:block">Features</a>
                    <a href="#security" class="hidden text-sm text-zinc-400 transition hover:text-white md:block">Security</a>
                    @if ($loggedInUrl)
                        <a href="{{ $loggedInUrl }}" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-500/20 transition hover:bg-indigo-400">
                            Go to Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg border border-white/10 px-4 py-2 text-sm font-medium text-zinc-200 transition hover:border-white/30 hover:text-white">
                            Login
                        </a>
                    @endif
                </div>
            </nav>
        </header>

        <main>
            {{-- Hero --}}
            <section class="lp-grid-bg">
                <div class="mx-auto max-w-6xl px-4 pb-16 pt-16 sm:px-6 lg:pt-24">
                    <div class="mx-auto max-w-3xl text-center">
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-zinc-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            Multi-tenant AI counsellor platform
                        </span>

                        <h1 class="mt-6 text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl lg:text-6xl">
                            AI Counsellor for Education, Healthcare and Service Organisations
                        </h1>

                        <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-zinc-400">
                            Automate student and visitor enquiries, qualify leads, and hand over to human counsellors when needed.
                        </p>

                        <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            @if ($loggedInUrl)
                                <a href="{{ $loggedInUrl }}" class="w-full rounded-xl bg-indigo-500 px-6 py-3 text-center text-base font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400 sm:w-auto">
                                    Go to Dashboard
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="w-full rounded-xl bg-indigo-500 px-6 py-3 text-center text-base font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400 sm:w-auto">
                                    Login
                                </a>
                            @endif
                            <a href="{{ $demoUrl }}" class="w-full rounded-xl border border-white/10 bg-white/5 px-6 py-3 text-center text-base font-medium text-zinc-200 transition hover:border-white/30 hover:text-white sm:w-auto">
                                Try Demo Widget
                            </a>
                        </div>
                    </div>

                    {{-- Role entry cards --}}
                    <div class="mx-auto mt-16 grid max-w-5xl gap-4 sm:grid-cols-3">
                        @php
                            $roleCards = [
                                ['title' => 'Platform Super Admin', 'desc' => 'Manage tenants, plans, AI operations and platform health.', 'href' => $loggedInUrl && auth()->user()?->isPlatformSuperAdmin() ? route('platform.overview') : route('login')],
                                ['title' => 'Tenant Admin', 'desc' => 'Configure branding, knowledge base, widget and team members.', 'href' => route('login')],
                                ['title' => 'Counsellor Workspace', 'desc' => 'Handle assigned leads, live chats and follow-ups.', 'href' => route('login')],
                            ];
                        @endphp
                        @foreach ($roleCards as $card)
                            <a href="{{ $card['href'] }}" class="group rounded-2xl border border-white/10 bg-white/[0.03] p-6 transition hover:border-indigo-400/40 hover:bg-white/[0.06]">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-base font-semibold text-white">{{ $card['title'] }}</h3>
                                    <svg class="h-4 w-4 text-zinc-500 transition group-hover:translate-x-0.5 group-hover:text-indigo-300" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M4 10h12M11 5l5 5-5 5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                                <p class="mt-2 text-sm leading-relaxed text-zinc-400">{{ $card['desc'] }}</p>
                                <span class="mt-4 inline-block text-sm font-medium text-indigo-300">Login &rarr;</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Features --}}
            <section id="features" class="border-t border-white/5 py-20">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div class="max-w-2xl">
                        <h2 class="text-3xl font-bold tracking-tight text-white">Everything you need to convert enquiries</h2>
                        <p class="mt-3 text-zinc-400">A complete AI-first front desk that knows when to bring a human in.</p>
                    </div>

                    @php
                        $features = [
                            ['title' => 'AI Chat Widget', 'desc' => 'Embeddable, branded chat widget that answers enquiries instantly on any website.'],
                            ['title' => 'Lead Capture', 'desc' => 'Automatically qualify and record leads from every conversation.'],
                            ['title' => 'Human Handoff', 'desc' => 'Seamlessly escalate to a human counsellor for live chat when needed.'],
                            ['title' => 'Tenant Branding', 'desc' => 'Each organisation gets its own logo, colours and assistant personality.'],
                            ['title' => 'Knowledge Base', 'desc' => 'Feed courses, fees, eligibility and documents so answers stay accurate.'],
                            ['title' => 'WhatsApp-ready Roadmap', 'desc' => 'Integration path for WhatsApp and messaging channels.'],
                        ];
                    @endphp
                    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($features as $feature)
                            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500/15 text-indigo-300">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="10" r="4" /></svg>
                                </div>
                                <h3 class="mt-4 text-base font-semibold text-white">{{ $feature['title'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-zinc-400">{{ $feature['desc'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Security / Trust --}}
            <section id="security" class="border-t border-white/5 py-20">
                <div class="mx-auto max-w-6xl px-4 sm:px-6">
                    <div class="grid items-start gap-10 lg:grid-cols-2">
                        <div>
                            <h2 class="text-3xl font-bold tracking-tight text-white">Secure by design</h2>
                            <p class="mt-3 text-zinc-400">Built for organisations that handle sensitive enquiries every day.</p>
                        </div>
                        <div class="grid gap-4">
                            @php
                                $trust = [
                                    ['title' => 'Multi-tenant isolation', 'desc' => 'Every organisation’s data, leads and conversations are strictly separated.'],
                                    ['title' => 'No API keys exposed in browser', 'desc' => 'AI credentials never reach the client — all calls run server-side.'],
                                    ['title' => 'Platform-managed AI credentials', 'desc' => 'Centrally managed provider keys with controlled usage and oversight.'],
                                ];
                            @endphp
                            @foreach ($trust as $item)
                                <div class="flex gap-4 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1.5l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9v-5l7-3zM9 12l5-5-1.4-1.4L9 9.2 7.4 7.6 6 9l3 3z" clip-rule="evenodd" /></svg>
                                    <div>
                                        <h3 class="text-base font-semibold text-white">{{ $item['title'] }}</h3>
                                        <p class="mt-1 text-sm leading-relaxed text-zinc-400">{{ $item['desc'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            {{-- CTA band --}}
            <section class="border-t border-white/5 py-20">
                <div class="mx-auto max-w-4xl px-4 text-center sm:px-6">
                    <h2 class="text-3xl font-bold tracking-tight text-white">Ready to get started?</h2>
                    <p class="mx-auto mt-3 max-w-xl text-zinc-400">Login to your workspace or explore the demo widget to see the AI counsellor in action.</p>
                    <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <a href="{{ $loggedInUrl ?? route('login') }}" class="w-full rounded-xl bg-indigo-500 px-6 py-3 text-center text-base font-semibold text-white shadow-lg shadow-indigo-500/25 transition hover:bg-indigo-400 sm:w-auto">
                            {{ $loggedInUrl ? 'Go to Dashboard' : 'Login' }}
                        </a>
                        <a href="{{ $demoUrl }}" class="w-full rounded-xl border border-white/10 bg-white/5 px-6 py-3 text-center text-base font-medium text-zinc-200 transition hover:border-white/30 hover:text-white sm:w-auto">
                            Try Demo Widget
                        </a>
                    </div>
                </div>
            </section>
        </main>

        {{-- Footer --}}
        <footer class="border-t border-white/5 py-10">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 sm:flex-row sm:px-6">
                <div class="flex items-center gap-3">
                    <img src="{{ asset(\App\Support\Branding::logoPath()) }}" alt="{{ config('app.name') }}" class="h-7 w-auto max-w-[120px] object-contain" />
                    <span class="text-sm text-zinc-500">&copy; {{ date('Y') }} {{ config('app.name', 'AI Counsellor') }}</span>
                </div>
                <p class="text-sm text-zinc-500">Powered by <span class="font-semibold text-zinc-300">SR Worlds AI</span></p>
            </div>
        </footer>
    </body>
</html>
