@php
    $flashes = [
        'success' => ['bg-green-50', 'text-green-800', 'border-green-200'],
        'error'   => ['bg-red-50', 'text-red-800', 'border-red-200'],
        'warning' => ['bg-amber-50', 'text-amber-800', 'border-amber-200'],
        'status'  => ['bg-blue-50', 'text-blue-800', 'border-blue-200'],
    ];
@endphp

@foreach ($flashes as $key => [$bg, $text, $border])
    @if (session($key))
        <div x-data="{ show: true }" x-show="show" x-transition
             class="mb-4 flex items-start gap-3 rounded-lg border {{ $border }} {{ $bg }} px-4 py-3">
            <p class="flex-1 text-sm font-medium {{ $text }}">{{ session($key) }}</p>
            <button @click="show = false" class="{{ $text }} opacity-60 hover:opacity-100">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif
@endforeach

@if ($errors->any() && ! $errors->hasBag('default'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
        <p class="text-sm font-medium text-red-800">{{ __('Please fix the following:') }}</p>
        <ul class="mt-1.5 list-disc list-inside text-sm text-red-700 space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
