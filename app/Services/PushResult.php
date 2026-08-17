<?php

namespace App\Services;

/**
 * Outcome of one multicast batch.
 */
readonly class PushResult
{
    /**
     * @param  list<string>  $staleTokens  Tokens FCM reported as invalid or unknown.
     */
    public function __construct(
        public int $sent = 0,
        public int $failed = 0,
        public array $staleTokens = [],
    ) {}

    public static function allFailed(int $count): self
    {
        return new self(sent: 0, failed: $count);
    }
}
