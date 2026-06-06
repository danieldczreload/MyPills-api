<?php

declare(strict_types=1);

namespace Shared\Application\Bus;

use Shared\Domain\Result;

interface QueryBus
{
    /**
     * @return Result<mixed>
     */
    public function ask(object $query): Result;
}
