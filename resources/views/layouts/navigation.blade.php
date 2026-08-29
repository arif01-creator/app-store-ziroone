<!-- Mobile backdrop -->
<div x-show="sidebarOpen"
     x-transition.opacity
     @click="sidebarOpen = false"
     class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"
     style="display: none"></div>

<aside class="fixed inset-y-0 start-0 z-50 w-64 bg-gray-900 flex flex-col transition-transform lg:translate-x-0"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

    <!-- Logo -->
    <div class="h-16 flex items-center px-6 border-b border-gray-800">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
            <img src="{{ asset('ziroone_play.png') }}" alt="Logo" class="w-10 h-10 fill-current text-gray-500" />
            {{-- <x-application-logo class="h-8 w-auto fill-current text-white" /> --}}
            <span class="text-white font-semibold text-sm leading-tight">{{ config('app.name') }}</span>
        </a>
    </div>

    <!-- Links -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
            <x-slot:icon>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </x-slot:icon>
            {{ __('Dashboard') }}
        </x-sidebar-link>

        <x-sidebar-link :href="route('apps.index')" :active="request()->routeIs('apps.*')">
            <x-slot:icon>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </x-slot:icon>
            {{ __('Apps') }}
        </x-sidebar-link>
    </nav>

    <!-- User -->
    <div class="border-t border-gray-800 p-3">
        <div class="px-3 py-2">
            <div class="text-sm font-medium text-white truncate">{{ Auth::user()?->name }}</div>
            <div class="text-xs text-gray-400 truncate">{{ Auth::user()?->email }}</div>
        </div>
        <x-sidebar-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">
            <x-slot:icon>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13 13 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
            </x-slot:icon>
            {{ __('Profile') }}
        </x-sidebar-link>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white transition">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</aside>
