<?php

declare(strict_types=1);

namespace CalendarIntegration\Application;

final readonly class CalendarEventRemovalResult
{
    public function __construct(
        public int $deleted,
        public int $failed,
        public bool $refreshFailed
    ) {
    }
}
