<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">{{ __('Apps') }}</h1>
                <p class="mt-0.5 text-sm text-gray-500">{{ __('Every app you distribute to clients.') }}</p>
            </div>
            <a href="{{ route('apps.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('New app') }}
            </a>
        </div>
    </x-slot>

    <x-card>
        <div class="px-5 py-3 border-b border-gray-200">
            <form method="GET" class="flex items-center gap-2">
                <div class="relative flex-1 max-w-sm">
                    <svg class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="search" name="search" value="{{ $search }}"
                           placeholder="{{ __('Search name, slug or package id…') }}"
                           class="w-full ps-9 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                </div>
                <button type="submit"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    {{ __('Search') }}
                </button>
                @if ($search !== '')
                    <a href="{{ route('apps.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>

        @if ($apps->isEmpty())
            <x-empty-state :title="$search !== '' ? __('No apps match that search.') : __('No apps yet.')"
                           :description="$search !== '' ? __('Try a different name, slug or package id.') : __('Create your first app to start distributing builds.')">
                @if ($search === '')
                    <x-slot:action>
                        <a href="{{ route('apps.create') }}"
                           class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                            {{ __('New app') }}
                        </a>
                    </x-slot:action>
                @endif
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-start font-medium">{{ __('App') }}</th>
                            <th class="px-5 py-3 text-start font-medium">{{ __('Package id') }}</th>
                            <th class="px-5 py-3 text-start font-medium">{{ __('Latest') }}</th>
                            <th class="px-5 py-3 text-end font-medium">{{ __('Versions') }}</th>
                            <th class="px-5 py-3 text-end font-medium">{{ __('Devices') }}</th>
                            <th class="px-5 py-3 text-start font-medium">{{ __('Status') }}</th>
                            <th class="px-5 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($apps as $app)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-app-icon :app="$app" class="h-9 w-9" />
                                        <div class="min-w-0">
                                            <a href="{{ route('apps.show', $app) }}"
                                               class="font-medium text-gray-900 hover:underline">{{ $app->name }}</a>
                                            <div class="text-xs text-gray-500">/{{ $app->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <code class="text-xs text-gray-600">{{ $app->package_id }}</code>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($app->latestActiveVersion)
                                        <span class="font-medium text-gray-900">{{ $app->latestActiveVersion->version_name }}</span>
                                        <span class="text-xs text-gray-500">({{ $app->latestActiveVersion->version_code }})</span>
                                    @else
                                        <span class="text-gray-400">{{ __('None') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-end text-gray-700">{{ $app->versions_count }}</td>
                                <td class="px-5 py-3 text-end text-gray-700">{{ $app->devices_count }}</td>
                                <td class="px-5 py-3">
                                    @if ($app->is_active)
                                        <x-badge color="green">{{ __('Active') }}</x-badge>
                                    @else
                                        <x-badge color="gray">{{ __('Paused') }}</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-end whitespace-nowrap">
                                    <a href="{{ route('apps.show', $app) }}" class="text-sm font-medium text-gray-900 hover:underline">{{ __('Manage') }}</a>
                                    <a href="{{ route('apps.edit', $app) }}" class="ms-3 text-sm text-gray-500 hover:text-gray-700">{{ __('Edit') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($apps->hasPages())
                <div class="px-5 py-3 border-t border-gray-200">{{ $apps->links() }}</div>
            @endif
        @endif
    </x-card>
</x-app-layout>
