@php
    use SimpleSoftwareIO\QrCode\Facades\QrCode;
@endphp

<x-card :title="__('Share link')" :description="__('Send this to a client, or have them scan it.')">
    <div class="px-5 py-5 space-y-4" x-data="{
            copied: false,
            copy() {
                navigator.clipboard.writeText(@js($publicUrl)).then(() => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                });
            },
         }">

        <div class="flex justify-center">
            <div class="rounded-lg border border-gray-200 bg-white p-3">
                {!! QrCode::size(168)->margin(0)->generate($publicUrl) !!}
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Public download page') }}</label>
            <div class="flex rounded-md shadow-sm">
                <input type="text" readonly value="{{ $publicUrl }}"
                       class="block w-full rounded-s-md border-gray-300 bg-gray-50 text-xs text-gray-700 focus:border-gray-900 focus:ring-gray-900"
                       onfocus="this.select()">
                <button type="button" @click="copy()"
                        class="inline-flex items-center gap-1.5 rounded-e-md border border-s-0 border-gray-300 bg-white px-3 text-xs font-medium text-gray-700 hover:bg-gray-50">
                    <template x-if="! copied">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </template>
                    <template x-if="copied">
                        <svg class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </template>
                    <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))"></span>
                </button>
            </div>
        </div>

        @unless ($app->is_active)
            <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                {{ __('This app is paused — the public page currently returns 404.') }}
            </p>
        @endunless
    </div>
</x-card>
