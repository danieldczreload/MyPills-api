<?php

declare(strict_types=1);

namespace CalendarIntegration\Application;

use CalendarIntegration\Domain\CalendarProvider;
use CalendarIntegration\Domain\CalendarProviderName;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CalendarProviderResolver
{
    public function __construct(
        #[Autowire(service: 'CalendarIntegration\Infrastructure\GoogleCalendarGateway')]
        private readonly CalendarProvider $googleGateway,
        #[Autowire(service: 'CalendarIntegration\Infrastructure\MicrosoftCalendarGateway')]
        private readonly CalendarProvider $microsoftGateway
    ) {
    }

    public function resolve(CalendarProviderName $provider): CalendarProvider
    {
        return match ($provider) {
            CalendarProviderName::GOOGLE => $this->googleGateway,
            CalendarProviderName::MICROSOFT => $this->microsoftGateway,
        };
    }

    /**
     * @throws \InvalidArgumentException When the provider is not supported.
     */
    public function resolveString(string $provider): CalendarProvider
    {
        $name = CalendarProviderName::tryFrom($provider);

        if ($name === null) {
            throw new \InvalidArgumentException(sprintf('Unsupported calendar provider "%s".', $provider));
        }

        return $this->resolve($name);
    }
}
