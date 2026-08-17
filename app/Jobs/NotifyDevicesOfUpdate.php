<?php

namespace App\Jobs;

use App\Models\AppVersion;
use App\Models\Device;
use App\Models\PushLog;
use App\Services\PushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

/**
 * Pushes "a new build is available" to every reachable device of one app.
 *
 * Runs once per uploaded version and always writes a single push_logs row
 * summarising what happened — including when FCM is not configured, so the
 * admin can see the attempt rather than nothing at all.
 */
class NotifyDevicesOfUpdate implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public AppVersion $version) {}

    public function handle(PushSender $sender): void
    {
        $version = $this->version->loadMissing('app');
        $app = $version->app;

        if ($app === null) {
            Log::warning('Skipping push — app was deleted', ['app_version_id' => $version->id]);

            return;
        }

        if (! $version->is_active) {
            Log::info('Skipping push for inactive version', ['app_version_id' => $version->id]);

            return;
        }

        $targeted = 0;
        $sent = 0;
        $failed = 0;
        $staleTokens = [];

        $message = $this->buildMessage();

        Device::query()
            ->where('app_id', $app->id)
            ->pushable()
            // Devices already on this build (or newer) don't need telling.
            ->where(fn ($query) => $query
                ->whereNull('current_version_code')
                ->orWhere('current_version_code', '<', $version->version_code))
            ->select(['id', 'fcm_token'])
            ->chunkById(PushSender::BATCH_SIZE, function ($devices) use ($message, $sender, &$targeted, &$sent, &$failed, &$staleTokens): void {
                $tokens = $devices->pluck('fcm_token')->filter()->unique()->values()->all();

                if ($tokens === []) {
                    return;
                }

                $targeted += count($tokens);

                $result = $sender->send($message, $tokens);

                $sent += $result->sent;
                $failed += $result->failed;
                $staleTokens = array_merge($staleTokens, $result->staleTokens);
            });

        $this->clearStaleTokens($staleTokens);

        PushLog::create([
            'app_version_id' => $version->id,
            'devices_targeted' => $targeted,
            'devices_sent' => $sent,
            'devices_failed' => $failed,
            'sent_at' => now(),
        ]);
    }

    private function buildMessage(): CloudMessage
    {
        $version = $this->version;
        $app = $version->app;

        $title = $version->is_force_update
            ? "Required update: {$app->name} {$version->version_name}"
            : "{$app->name} {$version->version_name} is available";

        $body = str($version->release_notes ?: 'Tap to install the latest version.')
            ->stripTags()
            ->squish()
            ->limit(160)
            ->value();

        return CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            // The Flutter client branches on this payload to show its own
            // update sheet and hit the download endpoint.
            ->withData([
                'type' => 'app_update',
                'app_slug' => (string) $app->slug,
                'package_id' => (string) $app->package_id,
                'version_name' => (string) $version->version_name,
                'version_code' => (string) $version->version_code,
                'is_force_update' => $version->is_force_update ? '1' : '0',
                'download_url' => route('api.versions.download', [
                    'api_key' => $app->api_key,
                    'version_code' => $version->version_code,
                ]),
            ])
            ->withAndroidConfig(
                AndroidConfig::new()->withHighMessagePriority()->withDefaultSound()
            );
    }

    /**
     * Null out tokens FCM told us are dead, so those devices show as
     * "push disabled" instead of silently never receiving anything.
     *
     * @param  list<string>  $tokens
     */
    private function clearStaleTokens(array $tokens): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if ($tokens === []) {
            return;
        }

        foreach (array_chunk($tokens, PushSender::BATCH_SIZE) as $chunk) {
            Device::query()
                ->where('app_id', $this->version->app_id)
                ->whereIn('fcm_token', $chunk)
                ->update(['fcm_token' => null]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('NotifyDevicesOfUpdate failed', [
            'app_version_id' => $this->version->id,
            'error' => $e->getMessage(),
        ]);
    }
}
