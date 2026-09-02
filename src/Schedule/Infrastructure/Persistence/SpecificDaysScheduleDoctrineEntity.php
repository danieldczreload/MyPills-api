<?php

declare(strict_types=1);

namespace Schedule\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class SpecificDaysScheduleDoctrineEntity extends ScheduleDoctrineEntity
{
    /**
     * @var array<int>
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private array $daysOfWeek;

    /**
     * @var array<array{hour: int, minute: int}>
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private array $timesOfDay;

    /**
     * @param array<int> $daysOfWeek
     * @param array<array{hour: int, minute: int}> $timesOfDay
     */
    public function __construct(
        string $id,
        string $medicationId,
        array $daysOfWeek,
        array $timesOfDay,
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        ?string $clientId,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        ?string $doseAmount = null,
        ?string $doseUnit = null
    ) {
        parent::__construct($id, $medicationId, $startDate, $endDate, $clientId, $createdAt, $updatedAt, $doseAmount, $doseUnit);
        $this->daysOfWeek = $daysOfWeek;
        $this->timesOfDay = $timesOfDay;
    }

    /**
     * @return array<int>
     */
    public function getDaysOfWeek(): array
    {
        return $this->daysOfWeek;
    }

    /**
     * @return array<array{hour: int, minute: int}>
     */
    public function getTimesOfDay(): array
    {
        return $this->timesOfDay;
    }
}
