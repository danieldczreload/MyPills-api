<?php

declare(strict_types=1);

namespace App\Tests\Profile\Application;

use PHPUnit\Framework\TestCase;
use Profile\Application\Command\CreateProfileCommand;
use Profile\Application\Command\CreateProfileHandler;
use Profile\Domain\ProfileRepository;

final class CreateProfileHandlerTest extends TestCase
{
    public function testCreateProfileValidationAndSuccess(): void
    {
        $repo = $this->createMock(ProfileRepository::class);
        $handler = new CreateProfileHandler($repo);

        // Empty name
        $res = $handler(new CreateProfileCommand('acc-1', '   ', new \DateTimeImmutable('1995-03-20'), 'male', null));
        self::assertTrue($res->isFailure());

        // Success
        $repo->expects(self::once())->method('save');
        $res = $handler(new CreateProfileCommand('acc-1', 'Alice', new \DateTimeImmutable('1995-03-20'), 'female', 'https://pic.jpg'));
        self::assertTrue($res->isSuccess());
        self::assertSame('Alice', $res->getValue()['name']);
        self::assertSame('female', $res->getValue()['gender']);
    }
}
