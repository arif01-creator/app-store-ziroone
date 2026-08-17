@props(['active' => false, 'icon' => null])

@php
$classes = $active
    ? 'flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium bg-gray-800 text-white'
    : 'flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">{{ $icon }}</svg>
    @endif
    {{ $slot }}
</a>
