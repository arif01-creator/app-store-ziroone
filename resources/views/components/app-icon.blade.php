@props(['app'])

@if ($app->icon_path)
    <img src="{{ Storage::disk('public')->url($app->icon_path) }}"
         alt="{{ $app->name }}"
         {{ $attributes->merge(['class' => 'rounded-lg object-cover bg-gray-100 shrink-0']) }}>
@else
    <div {{ $attributes->merge(['class' => 'rounded-lg bg-gray-900 text-white flex items-center justify-center font-semibold shrink-0']) }}>
        {{ Str::upper(Str::substr($app->name, 0, 1)) }}
    </div>
@endif
