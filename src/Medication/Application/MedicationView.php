<?php

declare(strict_types=1);

namespace Medication\Application;

use Medication\Domain\Medication;

final class MedicationView
{
    /**
     * @return array{id: string, profileId: string, name: string, instructions: ?string, photoUrl: ?string, clientId: ?string, form: string, colorToken: string, createdAt: string, updatedAt: string}
     */
    public static function toArray(Medication $medication): array
    {
        return [
            'id' => $medication->id()->value(),
            'profileId' => $medication->profileId()->value(),
            'name' => $medication->name(),
            'instructions' => $medication->instructions(),
            'photoUrl' => $medication->photoUrl(),
            'clientId' => $medication->clientId(),
            'form' => $medication->form(),
            'colorToken' => $medication->colorToken(),
            'createdAt' => $medication->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $medication->updatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
