<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('apps.show', $app) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ $app->name }}</a>
            <h1 class="mt-1 text-xl font-semibold text-gray-900">{{ __('Edit app') }}</h1>
        </div>
    </x-slot>

    <div class="max-w-3xl">
        <x-card>
            <form method="POST" action="{{ route('apps.update', $app) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('apps.partials.form', ['app' => $app])
            </form>
        </x-card>
    </div>
</x-app-layout>
