<?php

declare(strict_types=1);

namespace Shared\Application\Bus;

interface EventBus
{
    public function publish(object $event): void;
}
