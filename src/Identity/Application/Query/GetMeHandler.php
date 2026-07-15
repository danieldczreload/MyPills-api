<?php

declare(strict_types=1);

namespace Identity\Application\Query;

use Identity\Domain\AccountRepository;
use Shared\Domain\Result;
use Shared\Domain\Failure;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetMeHandler
{
    public function __construct(
        private readonly AccountRepository $accountRepository
    ) {
    }

    /**
     * @return Result<array{id: string, email: string, createdAt: string, updatedAt: string}>
     */
    public function __invoke(GetMeQuery $query): Result
    {
        $account = $this->accountRepository->findById($query->accountId);

        if ($account === null) {
            return Result::failure(Failure::notFound('Account not found.'));
        }

        return Result::success([
            'id' => $account->id()->value(),
            'email' => $account->email()->value(),
            'createdAt' => $account->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $account->updatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
