@php
    use Illuminate\Support\Facades\Storage;
    $publicUrl = route('public.apps.show', $app);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
                <x-app-icon :app="$app" class="h-12 w-12 text-lg" />
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold text-gray-900 truncate">{{ $app->name }}</h1>
                        @unless ($app->is_active)
                            <x-badge color="gray">{{ __('Paused') }}</x-badge>
                        @endunless
                    </div>
                    <p class="mt-0.5 text-sm text-gray-500">
                        <code class="text-xs">{{ $app->package_id }}</code>
                        @if ($app->latestActiveVersion)
                            <span class="mx-1.5 text-gray-300">&bull;</span>
                            {{ __('Latest') }}
                            <span class="font-medium text-gray-700">{{ $app->latestActiveVersion->version_name }}</span>
                            <span class="text-xs">({{ $app->latestActiveVersion->version_code }})</span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ $publicUrl }}" target="_blank" rel="noopener"
                   class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    {{ __('View public page') }}
                </a>
                <a href="{{ route('apps.edit', $app) }}"
                   class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                    {{ __('Edit app') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">

        {{-- Stat strip --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-tile :label="__('Registered devices')" :value="$stats['devices']" />
            <x-stat-tile :label="__('Outdated')" :value="$stats['outdated']"
                         :tone="$stats['outdated'] > 0 ? 'amber' : 'green'" />
            <x-stat-tile :label="__('Push disabled')" :value="$stats['no_push']"
                         :tone="$stats['no_push'] > 0 ? 'gray' : 'green'" />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Upload new version --}}
            <div class="lg:col-span-2">
                <x-card :title="__('Upload new version')"
                        :description="__('Uploading immediately queues a push to every device with notifications enabled.')">
                    <form method="POST" action="{{ route('apps.versions.store', $app) }}"
                          enctype="multipart/form-data" class="divide-y divide-gray-200">
                        @csrf

                        <div class="px-5 py-5 grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <x-input-label for="apk" :value="__('APK file')" />
                                <input id="apk" name="apk" type="file" accept=".apk,application/vnd.android.package-archive" required
                                       class="mt-1 block w-full text-sm text-gray-600 file:me-4 file:rounded-md file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-gray-800">
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ __('Up to :size MB.', ['size' => round(config('apk.max_upload_kb') / 1024)]) }}
                                    {{ __('Stored privately — never served from a public path.') }}
                                </p>
                                <x-input-error :messages="$errors->get('apk')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="version_name" :value="__('Version name')" />
                                <x-text-input id="version_name" name="version_name" type="text" class="mt-1 block w-full"
                                              :value="old('version_name')" placeholder="1.4.2" required />
                                <x-input-error :messages="$errors->get('version_name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="version_code" :value="__('Version code')" />
                                <x-text-input id="version_code" name="version_code" type="number" min="1" class="mt-1 block w-full"
                                              :value="old('version_code', $nextVersionCode)" required />
                                <p class="mt-1 text-xs text-gray-500">
                                    @if ($app->maxVersionCode() > 0)
                                        {{ __('Must be greater than :current.', ['current' => $app->maxVersionCode()]) }}
                                    @else
                                        {{ __('First build for this app.') }}
                                    @endif
                                </p>
                                <x-input-error :messages="$errors->get('version_code')" class="mt-2" />
                            </div>

                            <div class="sm:col-span-2">
                                <x-input-label for="release_notes" :value="__('Release notes')" />
                                <textarea id="release_notes" name="release_notes" rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                                          placeholder="{{ __('What changed in this build? Shown in the push notification and on the public page.') }}">{{ old('release_notes') }}</textarea>
                                <x-input-error :messages="$errors->get('release_notes')" class="mt-2" />
                            </div>

                            <div class="sm:col-span-2">
                                <label class="flex items-start gap-3">
                                    <input type="hidden" name="is_force_update" value="0">
                                    <input type="checkbox" name="is_force_update" value="1" @checked(old('is_force_update'))
                                           class="mt-0.5 rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                                    <span>
                                        <span class="block text-sm font-medium text-gray-900">{{ __('Force update') }}</span>
                                        <span class="block text-xs text-gray-500">{{ __('Tells the client to block use until the update is installed.') }}</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end px-5 py-4 bg-gray-50 rounded-b-lg">
                            <button type="submit"
                                    class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 transition">
                                {{ __('Upload & notify devices') }}
                            </button>
                        </div>
                    </form>
                </x-card>
            </div>

            {{-- Share link + QR --}}
            <div class="space-y-6">
                @include('apps.partials.share-link', ['app' => $app, 'publicUrl' => $publicUrl])
                @include('apps.partials.api-key', ['app' => $app])
            </div>
        </div>

        {{-- Version history --}}
        @include('apps.partials.versions', ['app' => $app, 'versions' => $versions])

        {{-- Devices --}}
        @include('apps.partials.devices', [
            'app' => $app,
            'devices' => $devices,
            'deviceSearch' => $deviceSearch,
            'deviceFilter' => $deviceFilter,
        ])
    </div>
</x-app-layout>
