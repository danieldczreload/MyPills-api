<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

enum DoseUnit: string
{
    case Microgram = 'mcg';
    case Milligram = 'mg';
    case Gram = 'g';
    case Milliliter = 'ml';
    case Drop = 'drop';
    case Teaspoon = 'tsp';
    case Tablespoon = 'tbsp';
    case InternationalUnit = 'iu';
    case Milliequivalent = 'meq';
    case Millimole = 'mmol';
    case Tablet = 'tablet';
    case Capsule = 'capsule';
    case Puff = 'puff';
    case Spray = 'spray';
    case Patch = 'patch';
    case Application = 'application';
    case Unit = 'unit';

    public function symbol(): string
    {
        return match ($this) {
            self::Microgram => 'µg',
            self::Milligram => 'mg',
            self::Gram => 'g',
            self::Milliliter => 'ml',
            self::Drop => 'drop',
            self::Teaspoon => 'tsp',
            self::Tablespoon => 'tbsp',
            self::InternationalUnit => 'IU',
            self::Milliequivalent => 'mEq',
            self::Millimole => 'mmol',
            self::Tablet => 'tablet',
            self::Capsule => 'capsule',
            self::Puff => 'puff',
            self::Spray => 'spray',
            self::Patch => 'patch',
            self::Application => 'application',
            self::Unit => 'unit',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Microgram => 'microgram',
            self::Milligram => 'milligram',
            self::Gram => 'gram',
            self::Milliliter => 'milliliter',
            self::Drop => 'drop',
            self::Teaspoon => 'teaspoon',
            self::Tablespoon => 'tablespoon',
            self::InternationalUnit => 'international unit',
            self::Milliequivalent => 'milliequivalent',
            self::Millimole => 'millimole',
            self::Tablet => 'tablet',
            self::Capsule => 'capsule',
            self::Puff => 'puff',
            self::Spray => 'spray',
            self::Patch => 'patch',
            self::Application => 'application',
            self::Unit => 'unit',
        };
    }

    public function isCountable(): bool
    {
        return match ($this) {
            self::Drop, self::Tablet, self::Capsule, self::Puff, self::Spray, self::Patch, self::Application, self::Unit => true,
            default => false,
        };
    }

    public function displayLabel(string $amount): string
    {
        if (!$this->isCountable()) {
            return $this->symbol();
        }

        $plural = $amount !== '1';

        return match ($this) {
            self::Drop => $plural ? 'drops' : 'drop',
            self::Tablet => $plural ? 'tablets' : 'tablet',
            self::Capsule => $plural ? 'capsules' : 'capsule',
            self::Puff => $plural ? 'puffs' : 'puff',
            self::Spray => $plural ? 'sprays' : 'spray',
            self::Patch => $plural ? 'patches' : 'patch',
            self::Application => $plural ? 'applications' : 'application',
            self::Unit => $plural ? 'units' : 'unit',
            default => $this->symbol(),
        };
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_map(static fn (self $unit): string => $unit->value, self::cases());
    }

    public static function fromInput(string $value): self
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['µ', 'μ'], 'u', $normalized);

        $aliases = [
            'ug' => self::Microgram,
            'mcg' => self::Microgram,
            'microgram' => self::Microgram,
            'micrograms' => self::Microgram,
            'mg' => self::Milligram,
            'milligram' => self::Milligram,
            'milligrams' => self::Milligram,
            'g' => self::Gram,
            'gram' => self::Gram,
            'grams' => self::Gram,
            'ml' => self::Milliliter,
            'milliliter' => self::Milliliter,
            'millilitre' => self::Milliliter,
            'milliliters' => self::Milliliter,
            'millilitres' => self::Milliliter,
            'drop' => self::Drop,
            'drops' => self::Drop,
            'gtt' => self::Drop,
            'gota' => self::Drop,
            'gotas' => self::Drop,
            'tsp' => self::Teaspoon,
            'teaspoon' => self::Teaspoon,
            'teaspoons' => self::Teaspoon,
            'cucharadita' => self::Teaspoon,
            'tbsp' => self::Tablespoon,
            'tablespoon' => self::Tablespoon,
            'tablespoons' => self::Tablespoon,
            'cucharada' => self::Tablespoon,
            'iu' => self::InternationalUnit,
            'ui' => self::InternationalUnit,
            'meq' => self::Milliequivalent,
            'mmol' => self::Millimole,
            'tablet' => self::Tablet,
            'tablets' => self::Tablet,
            'tab' => self::Tablet,
            'comprimido' => self::Tablet,
            'comprimidos' => self::Tablet,
            'pill' => self::Tablet,
            'pills' => self::Tablet,
            'capsule' => self::Capsule,
            'capsules' => self::Capsule,
            'cap' => self::Capsule,
            'capsula' => self::Capsule,
            'capsulas' => self::Capsule,
            'puff' => self::Puff,
            'puffs' => self::Puff,
            'inhalacion' => self::Puff,
            'inhalaciones' => self::Puff,
            'spray' => self::Spray,
            'sprays' => self::Spray,
            'patch' => self::Patch,
            'patches' => self::Patch,
            'parche' => self::Patch,
            'parches' => self::Patch,
            'application' => self::Application,
            'applications' => self::Application,
            'aplicacion' => self::Application,
            'aplicaciones' => self::Application,
            'unit' => self::Unit,
            'units' => self::Unit,
            'unidad' => self::Unit,
            'unidades' => self::Unit,
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        return self::from($normalized);
    }
}
