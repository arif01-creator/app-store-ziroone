<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('apps.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('Apps') }}</a>
            <h1 class="mt-1 text-xl font-semibold text-gray-900">{{ __('New app') }}</h1>
        </div>
    </x-slot>

    <div class="max-w-3xl">
        <x-card>
            <form method="POST" action="{{ route('apps.store') }}" enctype="multipart/form-data">
                @csrf
                @include('apps.partials.form', ['app' => $app])
            </form>
        </x-card>

        <p class="mt-4 text-sm text-gray-500">
            {{ __('An API key is generated automatically when the app is created — you will find it on the app screen, ready to paste into the Flutter client.') }}
        </p>
    </div>
</x-app-layout>
