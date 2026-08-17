<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Failure;
use Shared\Domain\Result;

final class ResultAndFailureTest extends TestCase
{
    public function testResultSuccess(): void
    {
        $res = Result::success('data-value');
        self::assertTrue($res->isSuccess());
        self::assertFalse($res->isFailure());
        self::assertSame('data-value', $res->getValue());

        $this->expectException(\LogicException::class);
        $res->getFailure();
    }

    public function testResultFailure(): void
    {
        $fail = Failure::notFound('Item not found', ['id' => '123']);
        $res = Result::failure($fail);
        self::assertFalse($res->isSuccess());
        self::assertTrue($res->isFailure());
        self::assertSame($fail, $res->getFailure());

        $this->expectException(\LogicException::class);
        $res->getValue();
    }

    public function testFailureFactoryMethods(): void
    {
        $f1 = Failure::validation('Invalid input', ['field' => 'name']);
        self::assertSame('VALIDATION', $f1->getType());
        self::assertSame('Invalid input', $f1->getMessage());
        self::assertSame(['field' => 'name'], $f1->getDetails());

        $f2 = Failure::unauthorized('Auth required');
        self::assertSame('UNAUTHORIZED', $f2->getType());

        $f3 = Failure::forbidden('Access forbidden');
        self::assertSame('FORBIDDEN', $f3->getType());

        $f4 = Failure::conflict('Resource conflict');
        self::assertSame('CONFLICT', $f4->getType());

        $f5 = Failure::badRequest('Bad request syntax');
        self::assertSame('BAD_REQUEST', $f5->getType());

        $f6 = Failure::server('Internal error');
        self::assertSame('SERVER_ERROR', $f6->getType());

        $f7 = Failure::custom('CUSTOM_ERROR', 'Custom error message');
        self::assertSame('CUSTOM_ERROR', $f7->getType());
    }
}
