<?php

namespace App\DataFixtures\Provider;

use Faker\Provider\Base;

/**
 * Fournit des méthodes Faker retournant DateTimeImmutable,
 * car les setters Doctrine sont typés \DateTimeImmutable.
 */
class FakerProvider extends Base
{
    public function dateTimeImmutableBetween(string $startDate = '-1 year', string $endDate = 'now'): \DateTimeImmutable
    {
        $start = strtotime($startDate);
        $end   = strtotime($endDate);
        return new \DateTimeImmutable('@' . mt_rand($start, $end));
    }

    public function dateTimeImmutableNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
