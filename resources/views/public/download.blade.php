<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $app->name }} &mdash; {{ __('Download') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">

        <div class="w-full max-w-md">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

                <div class="px-6 pt-8 pb-6 text-center">
                    <x-app-icon :app="$app" class="h-20 w-20 mx-auto text-3xl" />

                    <h1 class="mt-4 text-2xl font-semibold text-gray-900">{{ $app->name }}</h1>

                    @if ($version)
                        <div class="mt-1.5 flex items-center justify-center gap-2 text-sm text-gray-500">
                            <span>{{ __('Version') }} {{ $version->version_name }}</span>
                            <span class="text-gray-300">&bull;</span>
                            <span>{{ $version->formattedFileSize() }}</span>
                        </div>

                        @if ($version->is_force_update)
                            <div class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z" />
                                </svg>
                                {{ __('Required update') }}
                            </div>
                        @endif
                    @endif

                    @if ($app->description)
                        <p class="mt-4 text-sm text-gray-600 leading-relaxed">{{ $app->description }}</p>
                    @endif
                </div>

                @if ($version)
                    <div class="px-6 pb-6">
                        <form method="POST" action="{{ route('public.apps.download', $app) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gray-900 px-6 py-3.5 text-base font-semibold text-white hover:bg-gray-800 active:bg-gray-950 transition">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                {{ __('Download APK') }}
                            </button>
                        </form>

                        <p class="mt-3 text-center text-xs text-gray-500">
                            {{ __('Android only. You may need to allow installs from this browser.') }}
                        </p>
                    </div>

                    @if ($version->release_notes)
                        <div class="border-t border-gray-200 px-6 py-5 bg-gray-50">
                            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __("What's new") }}</h2>
                            <p class="mt-2 text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ $version->release_notes }}</p>
                        </div>
                    @endif
                @else
                    <div class="border-t border-gray-200 px-6 py-8 text-center">
                        <p class="text-sm text-gray-500">{{ __('No build has been published yet. Please check back shortly.') }}</p>
                    </div>
                @endif
            </div>

            <p class="mt-6 text-center text-xs text-gray-400">
                {{ __('Distributed privately by :name.', ['name' => config('app.name')]) }}
            </p>
        </div>
    </div>
</body>
</html>
