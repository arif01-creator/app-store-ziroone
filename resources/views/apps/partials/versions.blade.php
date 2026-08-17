<x-card :title="__('Version history')"
        :description="__('Pull a bad build with the toggle — history and push logs are kept.')">

    @if ($versions->isEmpty())
        <x-empty-state :title="__('No versions uploaded yet.')"
                       :description="__('Upload an APK above to publish the first build.')" />
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-3 text-start font-medium">{{ __('Version') }}</th>
                        <th class="px-5 py-3 text-end font-medium">{{ __('Size') }}</th>
                        <th class="px-5 py-3 text-start font-medium">{{ __('Release notes') }}</th>
                        <th class="px-5 py-3 text-start font-medium">{{ __('Push') }}</th>
                        <th class="px-5 py-3 text-start font-medium">{{ __('Uploaded') }}</th>
                        <th class="px-5 py-3 text-end font-medium">{{ __('Live') }}</th>
                        <th class="px-5 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($versions as $version)
                        @php $push = $version->pushLogs->first(); @endphp
                        <tr class="{{ $version->is_active ? '' : 'bg-gray-50/60' }} hover:bg-gray-50">
                            <td class="px-5 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900">{{ $version->version_name }}</span>
                                    <span class="text-xs text-gray-500">({{ $version->version_code }})</span>
                                    @if ($version->is_force_update)
                                        <x-badge color="red">{{ __('Force') }}</x-badge>
                                    @endif
                                    @if ($app->latestActiveVersion?->id === $version->id)
                                        <x-badge color="green">{{ __('Current') }}</x-badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-3 text-end whitespace-nowrap text-gray-600">{{ $version->formattedFileSize() }}</td>
                            <td class="px-5 py-3 max-w-xs">
                                @if ($version->release_notes)
                                    <span class="text-gray-600" title="{{ $version->release_notes }}">
                                        {{ Str::limit($version->release_notes, 70) }}
                                    </span>
                                @else
                                    <span class="text-gray-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @if ($push)
                                    <span class="text-xs text-gray-600">
                                        {{ $push->devices_sent }}/{{ $push->devices_targeted }} {{ __('sent') }}
                                        @if ($push->devices_failed > 0)
                                            <span class="text-red-600">({{ $push->devices_failed }} {{ __('failed') }})</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">{{ __('Not pushed') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-gray-600">
                                <span title="{{ $version->created_at?->toDayDateTimeString() }}">
                                    {{ $version->created_at?->diffForHumans() }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-end whitespace-nowrap">
                                <form method="POST" action="{{ route('apps.versions.update', [$app, $version]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $version->is_active ? 0 : 1 }}">
                                    <button type="submit" role="switch" aria-checked="{{ $version->is_active ? 'true' : 'false' }}"
                                            title="{{ $version->is_active ? __('Pull this build') : __('Make this build live again') }}"
                                            class="relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition
                                                   {{ $version->is_active ? 'bg-green-600' : 'bg-gray-300' }}">
                                        <span class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transition
                                                     {{ $version->is_active ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                    </button>
                                </form>
                            </td>
                            <td class="px-5 py-3 text-end whitespace-nowrap">
                                <a href="{{ route('apps.versions.download', [$app, $version]) }}"
                                   class="text-sm font-medium text-gray-600 hover:text-gray-900">{{ __('Download') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($versions->hasPages())
            <div class="px-5 py-3 border-t border-gray-200">{{ $versions->links() }}</div>
        @endif
    @endif
</x-card>
