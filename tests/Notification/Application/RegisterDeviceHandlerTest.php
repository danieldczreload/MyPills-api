<?php

declare(strict_types=1);

namespace App\Tests\Notification\Application;

use Notification\Application\Command\RegisterDeviceCommand;
use Notification\Application\Command\RegisterDeviceHandler;
use Notification\Domain\DeviceToken;
use Notification\Domain\DeviceTokenRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\UserId;

final class RegisterDeviceHandlerTest extends TestCase
{
    private DeviceTokenRepository&MockObject $repo;
    private RegisterDeviceHandler $handler;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(DeviceTokenRepository::class);
        $this->handler = new RegisterDeviceHandler($this->repo);
    }

    public function testEmptyTokenReturnsValidationFailure(): void
    {
        $cmd = new RegisterDeviceCommand('user-1', '', 'android', 'en-US');
        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('fcmToken cannot be empty.', $result->getFailure()->getMessage());
    }

    public function testInvalidTokenFormatReturnsValidationFailure(): void
    {
        $cmd = new RegisterDeviceCommand('user-1', "token\x00with_null", 'android', 'en-US');
        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('fcmToken has an invalid length or format.', $result->getFailure()->getMessage());
    }

    public function testInvalidPlatformReturnsValidationFailure(): void
    {
        $cmd = new RegisterDeviceCommand('user-1', 'valid-token', 'web', 'en-US');
        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('platform must be android or ios.', $result->getFailure()->getMessage());
    }

    public function testInvalidLocaleReturnsValidationFailure(): void
    {
        $cmd = new RegisterDeviceCommand('user-1', 'valid-token', 'android', 'invalid_locale_123');
        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('locale must use a valid locale such as es-MX.', $result->getFailure()->getMessage());
    }

    public function testThreeLetterRegionLocaleIsRejected(): void
    {
        $cmd = new RegisterDeviceCommand('user-1', 'valid-token', 'android', 'es-MEX');
        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('locale must use a valid locale such as es-MX.', $result->getFailure()->getMessage());
    }

    public function testEmptyLocaleReturnsValidationFailure(): void
    {
        $cmd = new RegisterDeviceCommand('user-1', 'valid-token', 'android', '');
        $result = ($this->handler)($cmd);
        self::assertTrue($result->isFailure());
        self::assertSame('locale must use a valid locale such as es-MX.', $result->getFailure()->getMessage());
    }

    public function testUnderscoreLocaleIsNormalized(): void
    {
        $this->repo->method('findByToken')->willReturn(null);
        $this->repo->expects(self::once())->method('save');

        $cmd = new RegisterDeviceCommand('user-1', 'valid-fcm-token', 'android', 'es_MX');
        $result = ($this->handler)($cmd);

        self::assertTrue($result->isSuccess());
        $data = $result->getValue();
        self::assertSame('es-MX', $data['locale']);
    }

    public function testMixedCaseLocaleIsCanonicalized(): void
    {
        $this->repo->method('findByToken')->willReturn(null);
        $this->repo->expects(self::once())->method('save');

        $cmd = new RegisterDeviceCommand('user-1', 'valid-fcm-token', 'ios', 'ES-mx');
        $result = ($this->handler)($cmd);

        self::assertTrue($result->isSuccess());
        $data = $result->getValue();
        self::assertSame('es-MX', $data['locale']);
    }

    public function testLanguageOnlyLocaleIsLowercased(): void
    {
        $this->repo->method('findByToken')->willReturn(null);
        $this->repo->expects(self::once())->method('save');

        $cmd = new RegisterDeviceCommand('user-1', 'valid-fcm-token', 'android', 'EN');
        $result = ($this->handler)($cmd);

        self::assertTrue($result->isSuccess());
        $data = $result->getValue();
        self::assertSame('en', $data['locale']);
    }

    public function testRegisterNewTokenSuccessfully(): void
    {
        $this->repo->method('findByToken')->willReturn(null);
        $this->repo->expects(self::once())->method('save');

        $cmd = new RegisterDeviceCommand('user-1', 'valid-fcm-token', 'android', 'es-MX');
        $result = ($this->handler)($cmd);

        self::assertTrue($result->isSuccess());
        $data = $result->getValue();
        self::assertSame('android', $data['platform']);
        self::assertSame('es-MX', $data['locale']);
    }

    public function testExistingTokenUpdatesWithNormalizedUnderscoreLocale(): void
    {
        $existing = new DeviceToken(
            'token-id-1',
            new UserId('user-1'),
            'valid-fcm-token',
            'android',
            'en-US',
            new \DateTimeImmutable()
        );

        $this->repo->method('findByToken')->willReturn($existing);
        $this->repo->expects(self::once())->method('save');

        $cmd = new RegisterDeviceCommand('user-1', 'valid-fcm-token', 'android', 'es_MX');
        $result = ($this->handler)($cmd);

        self::assertTrue($result->isSuccess());
        $data = $result->getValue();
        self::assertSame('es-MX', $data['locale']);
        self::assertSame('es-MX', $existing->locale());
    }

    public function testRegisterExistingTokenSameAccountUpdatesMetadata(): void
    {
        $existing = new DeviceToken(
            'token-id-1',
            new UserId('user-1'),
            'valid-fcm-token',
            'ios',
            'en-US',
            new \DateTimeImmutable()
        );

        $this->repo->method('findByToken')->willReturn($existing);
        $this->repo->expects(self::once())->method('save');

        $cmd = new RegisterDeviceCommand('user-1', 'valid-fcm-token', 'android', 'es-MX');
        $result = ($this->handler)($cmd);

        self::assertTrue($result->isSuccess());
        $data = $result->getValue();
        self::assertSame('android', $data['platform']);
        self::assertSame('es-MX', $data['locale']);
    }

    public function testRegisterExistingTokenDifferentAccountDeletesAndRecreates(): void
    {
        $existing = new DeviceToken(
            'token-id-1',
            new UserId('user-other'),
            'valid-fcm-token',
            'ios',
            'en-US',
            new \DateTimeImmutable()
        );

        $this->repo->method('findByToken')->willReturn($existing);
        $this->repo->expects(self::once())->method('delete')->with($existing);
        $this->repo->expects(self::once())->method('save');

        $cmd = new RegisterDeviceCommand('user-1', 'valid-fcm-token', 'android', 'es-MX');
        $result = ($this->handler)($cmd);

        self::assertTrue($result->isSuccess());
    }
}
