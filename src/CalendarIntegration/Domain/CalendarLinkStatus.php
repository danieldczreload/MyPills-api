<?php

declare(strict_types=1);

namespace CalendarIntegration\Domain;

enum CalendarLinkStatus: string
{
    case ACTIVE = 'active';
    case REAUTH_REQUIRED = 'reauth_required';
}
