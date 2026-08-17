<x-card :title="__('API key')" :description="__('Paste this into the Flutter client for this app.')">
    <div class="px-5 py-5 space-y-3"
         x-data="{
            revealed: false,
            copied: false,
            key: @js($app->api_key),
            copy() {
                navigator.clipboard.writeText(this.key).then(() => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                });
            },
         }">

        <div class="flex rounded-md shadow-sm">
            <input type="text" readonly
                   :value="revealed ? key : '•'.repeat(40)"
                   class="block w-full rounded-s-md border-gray-300 bg-gray-50 font-mono text-xs text-gray-700 focus:border-gray-900 focus:ring-gray-900"
                   onfocus="this.select()">
            <button type="button" @click="revealed = ! revealed"
                    class="border border-s-0 border-gray-300 bg-white px-3 text-xs font-medium text-gray-700 hover:bg-gray-50"
                    x-text="revealed ? @js(__('Hide')) : @js(__('Show'))"></button>
            <button type="button" @click="copy()"
                    class="rounded-e-md border border-s-0 border-gray-300 bg-white px-3 text-xs font-medium text-gray-700 hover:bg-gray-50"
                    x-text="copied ? @js(__('Copied')) : @js(__('Copy'))"></button>
        </div>

        <div class="rounded-md bg-gray-50 border border-gray-200 p-3 space-y-1.5">
            <p class="text-xs font-medium text-gray-700">{{ __('Endpoints for this app') }}</p>
            <div class="space-y-1 font-mono text-[11px] leading-relaxed text-gray-600 break-all">
                <div><span class="text-green-700 font-semibold">POST</span> {{ url("/api/v1/apps/{key}/register") }}</div>
                <div><span class="text-blue-700 font-semibold">GET</span> {{ url("/api/v1/apps/{key}/latest") }}</div>
                <div><span class="text-blue-700 font-semibold">GET</span> {{ url("/api/v1/apps/{key}/download/{version_code}") }}</div>
            </div>
        </div>

        <p class="text-xs text-gray-500">
            {{ __('Keep this out of the public download page — anyone holding it can register devices against this app.') }}
        </p>
    </div>
</x-card>
