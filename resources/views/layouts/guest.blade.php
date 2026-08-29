<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-950 text-slate-900">
        <div id="site-loader" aria-live="polite" aria-label="Loading page">
            <div class="flex flex-col items-center gap-3">
                <img src="{{ asset('ziroone_play.png') }}" alt="Loading logo" class="w-20 h-20" />
                <div class="loader-bar">
                    <span class="loader-progress"></span>
                </div>
            </div>
        </div>

        <div class="relative min-h-screen overflow-hidden bg-[radial-gradient(circle_at_top,_rgba(251,146,60,0.16),_transparent_28%),linear-gradient(135deg,_#020617_0%,_#111827_35%,_#0f172a_100%)]">
            <div class="absolute inset-0 bg-[linear-gradient(rgba(148,163,184,0.06)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,0.06)_1px,transparent_1px)] bg-[size:42px_42px]"></div>

            <div class="relative z-10 mx-auto grid min-h-screen max-w-7xl items-center gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:px-8">
                <div class="hidden lg:flex items-center justify-center">
                    <div class="max-w-lg rounded-[2rem] border border-white/10 bg-white/5 p-8 shadow-2xl shadow-orange-500/10 backdrop-blur-sm">
                        <div class="mb-8 flex items-center gap-4">
                            <img src="{{ asset('ziroone_play.png') }}" alt="Ziroone logo" class="h-16 w-16 rounded-2xl shadow-lg shadow-orange-500/20" />
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-300">App Management</p>
                                <h1 class="mt-2 text-3xl font-bold text-white">Welcome back</h1>
                            </div>
                        </div>

                        <div class="space-y-6 text-slate-200">
                            <p class="text-lg leading-relaxed text-slate-300">
                                Manage your apps, releases, devices, and push updates from one secure dashboard.
                            </p>

                            <div class="grid gap-3 text-sm text-slate-200/90 sm:grid-cols-2">
                                <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-4">
                                    <div class="mb-2 text-orange-300">Fast updates</div>
                                    <div>Ship app versions with confidence.</div>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-slate-900/40 p-4">
                                    <div class="mb-2 text-orange-300">Device sync</div>
                                    <div>Keep every client connected and informed.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-center">
                    <div class="w-full max-w-md rounded-[2rem] border border-slate-200/80 bg-white/90 p-6 shadow-2xl shadow-slate-950/20 backdrop-blur sm:p-8">
                        <div class="mb-8 flex items-center justify-center lg:hidden">
                            <a href="/" class="flex items-center gap-3">
                                <img src="{{ asset('ziroone_play.png') }}" alt="Logo" class="h-12 w-12 rounded-xl" />
                                <div class="text-left">
                                    <div class="text-[10px] font-semibold uppercase tracking-[0.3em] text-orange-500">Ziroone</div>
                                    <div class="text-sm font-bold text-slate-800">App Management</div>
                                </div>
                            </a>
                        </div>

                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
