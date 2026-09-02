<?php

declare(strict_types=1);

namespace Schedule\Application\Query;

use Shared\Domain\Result;
use Shared\Domain\ValueObject\DoseUnit;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetDoseUnitsHandler
{
    /**
     * @return Result<list<array{code: string, symbol: string, name: string, kind: string, suggestedForForms: list<string>}>>
     */
    public function __invoke(GetDoseUnitsQuery $query): Result
    {
        return Result::success(array_map(
            static fn (DoseUnit $unit): array => [
                'code' => $unit->value,
                'symbol' => $unit->symbol(),
                'name' => $unit->label(),
                'kind' => self::kind($unit),
                'suggestedForForms' => self::suggestedForForms($unit),
            ],
            DoseUnit::cases()
        ));
    }

    private static function kind(DoseUnit $unit): string
    {
        return match ($unit) {
            DoseUnit::Microgram, DoseUnit::Milligram, DoseUnit::Gram => 'mass',
            DoseUnit::Milliliter => 'volume',
            DoseUnit::Drop, DoseUnit::Teaspoon, DoseUnit::Tablespoon => 'household',
            DoseUnit::InternationalUnit, DoseUnit::Milliequivalent, DoseUnit::Millimole => 'special',
            DoseUnit::Tablet, DoseUnit::Capsule, DoseUnit::Puff, DoseUnit::Spray, DoseUnit::Patch, DoseUnit::Application, DoseUnit::Unit => 'count',
        };
    }

    /**
     * @return list<string>
     */
    private static function suggestedForForms(DoseUnit $unit): array
    {
        return match ($unit) {
            DoseUnit::Microgram, DoseUnit::Milligram, DoseUnit::Gram => ['pill', 'tablet', 'capsule', 'powder', 'injection'],
            DoseUnit::Milliliter => ['liquid', 'syrup', 'injection', 'drops', 'spray'],
            DoseUnit::Drop => ['drops', 'liquid', 'eye_drops', 'ear_drops'],
            DoseUnit::Teaspoon, DoseUnit::Tablespoon => ['liquid', 'syrup'],
            DoseUnit::InternationalUnit => ['injection', 'liquid', 'capsule', 'drop'],
            DoseUnit::Milliequivalent, DoseUnit::Millimole => ['liquid', 'injection', 'powder'],
            DoseUnit::Tablet => ['pill', 'tablet'],
            DoseUnit::Capsule => ['capsule'],
            DoseUnit::Puff => ['inhaler'],
            DoseUnit::Spray => ['spray', 'nasal_spray'],
            DoseUnit::Patch => ['patch'],
            DoseUnit::Application => ['cream', 'ointment', 'gel', 'lotion'],
            DoseUnit::Unit => ['pill', 'tablet', 'capsule', 'injection', 'other'],
        };
    }
}
