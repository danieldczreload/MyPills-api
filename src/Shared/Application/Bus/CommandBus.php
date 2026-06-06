<?php

declare(strict_types=1);

namespace Shared\Application\Bus;

use Shared\Domain\Result;

interface CommandBus
{
    /**
     * @return Result<mixed>
     */
    public function dispatch(object $command): Result;
}
