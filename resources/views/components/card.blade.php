@props(['title' => null, 'description' => null])

<div {{ $attributes->merge(['class' => 'bg-white shadow-sm rounded-lg border border-gray-200']) }}>
    @if ($title || isset($actions))
        <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-gray-200">
            <div>
                @if ($title)
                    <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-gray-500">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="shrink-0">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</div>
