<?php

declare(strict_types=1);

namespace Identity\Application\Query;

use Shared\Domain\ValueObject\UserId;

final class GetMeQuery
{
    public function __construct(
        public readonly UserId $accountId
    ) {
    }
}
