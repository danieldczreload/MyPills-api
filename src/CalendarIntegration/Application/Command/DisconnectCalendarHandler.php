<?php

declare(strict_types=1);

namespace CalendarIntegration\Application\Command;

use CalendarIntegration\Application\CalendarProviderResolver;
use CalendarIntegration\Domain\CalendarEventMappingRepository;
use CalendarIntegration\Domain\CalendarLinkRepository;
use CalendarIntegration\Domain\CalendarProviderName;
use Profile\Domain\ProfileRepository;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Domain\TokenVault;
use Shared\Domain\ValueObject\ProfileId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DisconnectCalendarHandler
{
    public function __construct(
        private readonly CalendarLinkRepository $calendarLinkRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly CalendarEventMappingRepository $mappingRepository,
        private readonly CalendarProviderResolver $providerResolver,
        private readonly TokenVault $tokenVault
    ) {
    }

    /**
     * @return Result<null>
     */
    public function __invoke(DisconnectCalendarCommand $command): Result
    {
        if (!CalendarProviderName::isSupported($command->provider)) {
            return Result::failure(Failure::validation('Invalid provider.'));
        }

        $profileId = new ProfileId($command->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        $link = $this->calendarLinkRepository->findByProfileAndProvider($profileId, $command->provider);

        if ($link === null) {
            return Result::failure(Failure::notFound('Calendar link not found.'));
        }

        $mappings = $this->mappingRepository->findByProfileAndProvider($profileId, $command->provider);
        if ($mappings !== []) {
            try {
                $gateway = $this->providerResolver->resolveString($command->provider);
                $accessToken = $gateway->refreshAccessToken(
                    $this->tokenVault->decrypt($link->encryptedRefreshToken())
                )->accessToken();
            } catch (\Throwable) {
                $link->markReauthorizationRequired();
                $this->calendarLinkRepository->save($link);

                return Result::failure(Failure::custom(
                    'CALENDAR_DISCONNECT_FAILED',
                    'The calendar events could not be removed. Reauthorize the calendar and try again.'
                ));
            }

            $failed = 0;
            foreach ($mappings as $mapping) {
                try {
                    $gateway->deleteEvent($accessToken, $mapping->externalEventId());
                    $this->mappingRepository->delete($mapping);
                } catch (\Throwable) {
                    ++$failed;
                }
            }

            if ($failed > 0) {
                return Result::failure(Failure::custom(
                    'CALENDAR_DISCONNECT_FAILED',
                    'Some calendar events could not be removed. Try again later.',
                    ['failed' => $failed]
                ));
            }
        }

        $this->calendarLinkRepository->delete($link);

        return Result::success();
    }
}
