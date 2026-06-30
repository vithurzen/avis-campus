<?php

namespace App\Service;

/**
 * Immutable result of a content-moderation analysis.
 */
final readonly class ModerationResult
{
    public function __construct(
        public ModerationVerdict $verdict,
        public float $score = 0.0,
        public ?string $suggestedRewrite = null,
    ) {
    }

    public static function safe(): self
    {
        return new self(ModerationVerdict::Safe);
    }

    public function isSafe(): bool
    {
        return $this->verdict === ModerationVerdict::Safe;
    }

    public function isAggressive(): bool
    {
        return $this->verdict === ModerationVerdict::Aggressive;
    }

    public function needsReview(): bool
    {
        return $this->verdict === ModerationVerdict::NeedsReview;
    }
}
