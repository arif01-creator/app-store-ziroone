@props(['color' => 'gray'])

@php
$colors = [
    'gray'   => 'bg-gray-100 text-gray-700 ring-gray-500/20',
    'green'  => 'bg-green-100 text-green-800 ring-green-600/20',
    'red'    => 'bg-red-100 text-red-800 ring-red-600/20',
    'amber'  => 'bg-amber-100 text-amber-800 ring-amber-600/20',
    'blue'   => 'bg-blue-100 text-blue-800 ring-blue-600/20',
    'indigo' => 'bg-indigo-100 text-indigo-800 ring-indigo-600/20',
];
$classes = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '
    . ($colors[$color] ?? $colors['gray']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>
