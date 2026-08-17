<x-card :title="__('Devices')"
        :description="__('Every install that has checked in. Click a label to rename it.')">

    <div class="px-5 py-3 border-b border-gray-200">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[16rem] max-w-sm">
                <svg class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="search" name="device_search" value="{{ $deviceSearch }}"
                       placeholder="{{ __('Search label, model or install id…') }}"
                       class="w-full ps-9 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
            </div>

            <select name="device_filter"
                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                <option value="">{{ __('All devices') }}</option>
                <option value="outdated" @selected($deviceFilter === 'outdated')>{{ __('Outdated only') }}</option>
                <option value="no_push" @selected($deviceFilter === 'no_push')>{{ __('Push disabled only') }}</option>
            </select>

            <button type="submit"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                {{ __('Filter') }}
            </button>

            @if ($deviceSearch !== '' || $deviceFilter !== '')
                <a href="{{ route('apps.show', $app) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Clear') }}</a>
            @endif
        </form>
    </div>

    @if ($devices->isEmpty())
        <x-empty-state
            :title="($deviceSearch !== '' || $deviceFilter !== '') ? __('No devices match those filters.') : __('No devices have checked in yet.')"
            :description="($deviceSearch !== '' || $deviceFilter !== '')
                ? __('Try clearing the search or filter.')
                : __('Devices appear here the first time the Flutter app calls the register endpoint.')" />
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-3 text-start font-medium">{{ __('Client') }}</th>
                        <th class="px-5 py-3 text-start font-medium">{{ __('Device') }}</th>
                        <th class="px-5 py-3 text-start font-medium">{{ __('Android') }}</th>
                        <th class="px-5 py-3 text-start font-medium">{{ __('Installed version') }}</th>
                        <th class="px-5 py-3 text-start font-medium">{{ __('Status') }}</th>
                        <th class="px-5 py-3 text-start font-medium">{{ __('Last seen') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($devices as $device)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-5 py-3">
                                <div x-data="{ editing: false }">
                                    <button type="button" x-show="! editing" @click="editing = true; $nextTick(() => $refs.input.focus())"
                                            class="text-start group">
                                        <span class="font-medium {{ $device->client_label ? 'text-gray-900' : 'text-gray-400 italic' }} group-hover:underline">
                                            {{ $device->client_label ?: __('Unlabelled') }}
                                        </span>
                                        <span class="block text-[11px] font-mono text-gray-400">{{ Str::limit($device->install_uuid, 18) }}</span>
                                    </button>

                                    <form x-show="editing" x-cloak method="POST"
                                          action="{{ route('apps.devices.update', [$app, $device]) }}"
                                          class="flex items-center gap-1.5">
                                        @csrf
                                        @method('PATCH')
                                        <input x-ref="input" type="text" name="client_label"
                                               value="{{ $device->client_label }}"
                                               placeholder="{{ __('e.g. Rahim - Accounts') }}"
                                               @keydown.escape.prevent="editing = false"
                                               class="w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900">
                                        <button type="submit"
                                                class="rounded-md bg-gray-900 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-gray-800">
                                            {{ __('Save') }}
                                        </button>
                                        <button type="button" @click="editing = false"
                                                class="text-xs text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</button>
                                    </form>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-700">{{ $device->device_model ?: '—' }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $device->android_version ?: '—' }}</td>
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
                                        <x-badge color="gray" title="{{ __('No FCM token — this device will not receive push notifications.') }}">
                                            {{ __('Push disabled') }}
                                        </x-badge>
                                    @endif

                                    @unless ($device->is_active)
                                        <x-badge color="gray">{{ __('Retired') }}</x-badge>
                                    @endunless
                                </div>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-gray-600">
                                @if ($device->last_seen_at)
                                    <span title="{{ $device->last_seen_at->toDayDateTimeString() }}">
                                        {{ $device->last_seen_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-gray-400">{{ __('Never') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($devices->hasPages())
            <div class="px-5 py-3 border-t border-gray-200">{{ $devices->links() }}</div>
        @endif
    @endif
</x-card>
