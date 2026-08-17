<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">{{ __('Dashboard') }}</h1>
            <p class="mt-0.5 text-sm text-gray-500">{{ __('Everything you distribute, at a glance.') }}</p>
        </div>
    </x-slot>

    <div class="space-y-6">

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-tile :label="__('Apps')" :value="$stats['apps']"
                         :hint="__(':count active', ['count' => $stats['active_apps']])" />
            <x-stat-tile :label="__('Devices')" :value="$stats['devices']"
                         :hint="__(':count with push disabled', ['count' => $stats['no_push']])" />
            <x-stat-tile :label="__('Outdated devices')" :value="$stats['outdated']"
                         :tone="$stats['outdated'] > 0 ? 'amber' : 'green'"
                         :hint="__('Behind their app\'s latest build')" />
            <x-stat-tile :label="__('Up to date')" :value="$stats['up_to_date']" tone="green" />
        </div>

        <x-card :title="__('Recent check-ins')"
                :description="__('The last 10 devices to call the register endpoint.')">

            @if ($recentCheckIns->isEmpty())
                <x-empty-state :title="__('No check-ins yet.')"
                               :description="__('Devices appear here once a Flutter client calls /api/v1/apps/{api_key}/register.')" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-5 py-3 text-start font-medium">{{ __('App') }}</th>
                                <th class="px-5 py-3 text-start font-medium">{{ __('Client') }}</th>
                                <th class="px-5 py-3 text-start font-medium">{{ __('Device') }}</th>
                                <th class="px-5 py-3 text-start font-medium">{{ __('Version') }}</th>
                                <th class="px-5 py-3 text-start font-medium">{{ __('Status') }}</th>
                                <th class="px-5 py-3 text-start font-medium">{{ __('Last seen') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($recentCheckIns as $device)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3">
                                        @if ($device->app)
                                            <a href="{{ route('apps.show', $device->app) }}"
                                               class="flex items-center gap-2.5 font-medium text-gray-900 hover:underline">
                                                <x-app-icon :app="$device->app" class="h-7 w-7 text-xs" />
                                                {{ $device->app->name }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="{{ $device->client_label ? 'text-gray-900' : 'text-gray-400 italic' }}">
                                            {{ $device->client_label ?: __('Unlabelled') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-gray-700">{{ $device->device_model ?: '—' }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        @if ($device->current_version_code)
                                            <span class="text-gray-900">{{ $device->current_version_name ?: '—' }}</span>
                                            <span class="text-xs text-gray-500">({{ $device->current_version_code }})</span>
                                        @else
                                            <span class="text-gray-400">{{ __('Unknown') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            @if ($device->isOutdated())
                                                <x-badge color="amber">{{ __('Outdated') }}</x-badge>
                                            @else
                                                <x-badge color="green">{{ __('Up to date') }}</x-badge>
                                            @endif
                                            @if (! $device->fcm_token)
                                                <x-badge color="gray">{{ __('Push disabled') }}</x-badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-gray-600">
                                        <span title="{{ $device->last_seen_at?->toDayDateTimeString() }}">
                                            {{ $device->last_seen_at?->diffForHumans() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
