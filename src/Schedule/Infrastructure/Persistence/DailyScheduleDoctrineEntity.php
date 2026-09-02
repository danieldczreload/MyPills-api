<?php

declare(strict_types=1);

namespace Schedule\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class DailyScheduleDoctrineEntity extends ScheduleDoctrineEntity
{
    /**
     * @var array<array{hour: int, minute: int}>
     */
    #[ORM\Column(type: 'json')]
    private array $timesOfDay;

    /**
     * @param array<array{hour: int, minute: int}> $timesOfDay
     */
    public function __construct(
        string $id,
        string $medicationId,
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
        $this->timesOfDay = $timesOfDay;
    }

    /**
     * @return array<array{hour: int, minute: int}>
     */
    public function getTimesOfDay(): array
    {
        return $this->timesOfDay;
    }

    /**
     * @param array<array{hour: int, minute: int}> $timesOfDay
     */
    public function setTimesOfDay(array $timesOfDay): void
    {
        $this->timesOfDay = $timesOfDay;
    }
}
