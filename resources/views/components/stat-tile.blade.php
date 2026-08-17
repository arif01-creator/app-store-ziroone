@props(['label', 'value', 'tone' => 'gray', 'hint' => null])

@php
$tones = [
    'gray'  => 'text-gray-900',
    'green' => 'text-green-700',
    'amber' => 'text-amber-700',
    'red'   => 'text-red-700',
    'blue'  => 'text-blue-700',
];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-lg border border-gray-200 shadow-sm px-5 py-4']) }}>
    <div class="text-sm font-medium text-gray-500">{{ $label }}</div>
    <div class="mt-1 text-2xl font-semibold {{ $tones[$tone] ?? $tones['gray'] }}">{{ $value }}</div>
    @if ($hint)
        <div class="mt-0.5 text-xs text-gray-500">{{ $hint }}</div>
    @endif
</div>
