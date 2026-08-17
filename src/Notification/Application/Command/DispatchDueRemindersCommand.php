<?php

declare(strict_types=1);

namespace Notification\Application\Command;

final class DispatchDueRemindersCommand
{
    public function __construct(
        public readonly ?\DateTimeImmutable $referenceTime = null
    ) {
    }
}
