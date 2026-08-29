<x-guest-layout>
    <div class="mb-8 text-center lg:text-left">
        <div class="mb-3 flex items-center justify-center lg:justify-start">
            <img src="{{ asset('ziroone_play.png') }}" alt="Ziroone logo" class="h-14 w-14 rounded-2xl shadow-lg shadow-orange-500/20" />
        </div>
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-orange-500">Secure access</p>
        <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900">Sign in</h2>
        <p class="mt-2 text-sm text-slate-500">Welcome back! Please enter your details below.</p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email address')" class="text-sm font-medium text-slate-700" />
            <x-text-input id="email" class="mt-2 block w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 shadow-sm transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" class="text-sm font-medium text-slate-700" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-orange-600 transition hover:text-orange-500">Forgot password?</a>
                @endif
            </div>

            <x-text-input id="password" class="mt-2 block w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 shadow-sm transition focus:border-orange-400 focus:ring-4 focus:ring-orange-100"
                        type="password"
                        name="password"
                        required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-3 pt-1">
            <label for="remember_me" class="inline-flex items-center gap-3 text-sm text-slate-600">
                <input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-orange-500 shadow-sm focus:ring-orange-500" name="remember">
                <span>{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="pt-2">
            <x-primary-button class="w-full justify-center rounded-2xl bg-slate-900 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-slate-900/20 transition hover:bg-slate-800 focus:ring-4 focus:ring-slate-200">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
