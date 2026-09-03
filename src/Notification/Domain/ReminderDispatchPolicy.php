<?php

declare(strict_types=1);

namespace Notification\Domain;

final class ReminderDispatchPolicy
{
    public const int MAX_ANTICIPATION_MINUTES = 15;

    public const int LOOKBACK_MINUTES = 10;

    public static function isDue(
        \DateTimeImmutable $scheduledAt,
        int $minutesBefore,
        \DateTimeImmutable $now
    ): bool {
        $fireAt = $scheduledAt->modify(sprintf('-%d minutes', $minutesBefore));
        $lookbackFrom = $now->modify(sprintf('-%d minutes', self::LOOKBACK_MINUTES));

        return $fireAt <= $now && $fireAt >= $lookbackFrom;
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public static function queryWindow(\DateTimeImmutable $now): array
    {
        $from = $now->modify(sprintf('-%d minutes', self::LOOKBACK_MINUTES + self::MAX_ANTICIPATION_MINUTES));
        $to = $now->modify(sprintf('+%d minutes', self::MAX_ANTICIPATION_MINUTES));

        return [$from, $to];
    }
}
