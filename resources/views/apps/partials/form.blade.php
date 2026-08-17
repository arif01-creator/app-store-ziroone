@php
    /** @var \App\Models\App $app */
    $isNew = ! $app->exists;
@endphp

<div x-data="{
        name: @js(old('name', $app->name)),
        slug: @js(old('slug', $app->slug)),
        slugTouched: {{ $isNew ? 'false' : 'true' }},
        slugify(value) {
            return value.toString().toLowerCase().trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        },
        syncSlug() {
            if (! this.slugTouched) this.slug = this.slugify(this.name);
        },
     }"
     class="divide-y divide-gray-200">

    <div class="px-5 py-5 grid gap-5 sm:grid-cols-2">
        <div class="sm:col-span-1">
            <x-input-label for="name" :value="__('App name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                          x-model="name" @input="syncSlug()" required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="sm:col-span-1">
            <x-input-label for="slug" :value="__('Slug')" />
            <div class="mt-1 flex rounded-md shadow-sm">
                <span class="inline-flex items-center rounded-s-md border border-e-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500">
                    /apps/
                </span>
                <input id="slug" name="slug" type="text" x-model="slug" @input="slugTouched = true"
                       class="block w-full rounded-e-md border-gray-300 text-sm focus:border-gray-900 focus:ring-gray-900">
            </div>
            <p class="mt-1 text-xs text-gray-500">{{ __('Used in the public download URL. Auto-filled from the name.') }}</p>
            <x-input-error :messages="$errors->get('slug')" class="mt-2" />
        </div>

        <div class="sm:col-span-2">
            <x-input-label for="package_id" :value="__('Android package id')" />
            <x-text-input id="package_id" name="package_id" type="text" class="mt-1 block w-full font-mono text-sm"
                          :value="old('package_id', $app->package_id)" placeholder="com.ziroone.crm" required />
            <p class="mt-1 text-xs text-gray-500">{{ __('Must match applicationId in the Flutter build.gradle.') }}</p>
            <x-input-error :messages="$errors->get('package_id')" class="mt-2" />
        </div>

        <div class="sm:col-span-2">
            <x-input-label for="description" :value="__('Description')" />
            <textarea id="description" name="description" rows="3"
                      class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-900 focus:ring-gray-900"
                      placeholder="{{ __('Shown on the public download page. Optional.') }}">{{ old('description', $app->description) }}</textarea>
            <x-input-error :messages="$errors->get('description')" class="mt-2" />
        </div>

        <div class="sm:col-span-2">
            <x-input-label for="icon" :value="__('Icon')" />
            <div class="mt-1 flex items-center gap-4">
                @if ($app->icon_path)
                    <x-app-icon :app="$app" class="h-14 w-14 text-xl" />
                @endif
                <input id="icon" name="icon" type="file" accept="image/png,image/jpeg,image/webp"
                       class="block w-full text-sm text-gray-600 file:me-4 file:rounded-md file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-gray-800">
            </div>
            <p class="mt-1 text-xs text-gray-500">{{ __('PNG, JPG or WebP, up to 2 MB.') }}@if ($app->icon_path) {{ __('Uploading a new icon replaces the current one.') }} @endif</p>
            <x-input-error :messages="$errors->get('icon')" class="mt-2" />
        </div>

        <div class="sm:col-span-2">
            <label class="flex items-start gap-3">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $app->is_active ?? true))
                       class="mt-0.5 rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                <span>
                    <span class="block text-sm font-medium text-gray-900">{{ __('Active') }}</span>
                    <span class="block text-xs text-gray-500">{{ __('Inactive apps stay in the list but are hidden from the public download page.') }}</span>
                </span>
            </label>
            <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 px-5 py-4 bg-gray-50 rounded-b-lg">
        <a href="{{ $app->exists ? route('apps.show', $app) : route('apps.index') }}"
           class="text-sm font-medium text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
        <button type="submit"
                class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 transition">
            {{ $app->exists ? __('Save changes') : __('Create app') }}
        </button>
    </div>
</div>
