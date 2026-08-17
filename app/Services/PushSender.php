<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Throwable;

/**
 * Thin wrapper around kreait's Messaging client.
 *
 * Two reasons this exists rather than injecting Messaging directly:
 *
 *  1. Firebase credentials are optional. On a box with no service-account JSON
 *     the container throws the moment Messaging is resolved — which would kill
 *     the push job before it could record what it tried to do. Here, an
 *     unconfigured install is reported as a clean batch failure instead.
 *  2. It gives the job a single seam to fake in tests.
 */
class PushSender
{
    /** FCM rejects multicast batches larger than this. */
    public const BATCH_SIZE = 500;

    public function isConfigured(): bool
    {
        $credentials = config('firebase.projects.'.config('firebase.default').'.credentials');

        if (blank($credentials)) {
            return false;
        }

        // A decoded service account array, or a JSON string, is already usable.
        if (is_array($credentials)) {
            return true;
        }

        return is_file($credentials) || is_file(base_path($credentials));
    }

    /**
     * @param  list<string>  $tokens
     */
    public function send(CloudMessage $message, array $tokens): PushResult
    {
        if ($tokens === []) {
            return new PushResult;
        }

        if (! $this->isConfigured()) {
            Log::warning('FCM is not configured — push skipped. Set FIREBASE_CREDENTIALS in .env.', [
                'tokens' => count($tokens),
            ]);

            return PushResult::allFailed(count($tokens));
        }

        try {
            $report = app(Messaging::class)->sendMulticast($message, $tokens);

            return new PushResult(
                sent: $report->successes()->count(),
                failed: $report->failures()->count(),
                staleTokens: array_values(array_merge(
                    $report->invalidTokens(),
                    $report->unknownTokens(),
                )),
            );
        } catch (Throwable $e) {
            Log::error('FCM multicast failed', [
                'batch_size' => count($tokens),
                'error' => $e->getMessage(),
            ]);

            return PushResult::allFailed(count($tokens));
        }
    }
}
