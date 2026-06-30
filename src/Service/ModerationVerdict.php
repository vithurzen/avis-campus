<?php

namespace App\Service;

/**
 * Possible outcomes returned by the content-moderation analysis.
 */
enum ModerationVerdict: string
{
    case Safe = 'safe';
    case Aggressive = 'aggressive';
    case NeedsReview = 'needs_review';

    /**
     * Maps a raw API/string value to a verdict. An unknown-but-present value is
     * treated cautiously as NeedsReview so a human looks at it.
     */
    public static function fromApi(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::NeedsReview;
    }
}
