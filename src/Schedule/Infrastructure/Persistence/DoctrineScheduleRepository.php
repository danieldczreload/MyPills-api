<?php

declare(strict_types=1);

namespace Schedule\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Schedule\Domain\Schedule;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\DailyIntervalSchedule;
use Schedule\Domain\SpecificDaysSchedule;
use Schedule\Domain\ValueObject\TimeOfDay;
use Schedule\Domain\ScheduleRepository;
use Shared\Domain\ValueObject\Dose;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ScheduleId;

final class DoctrineScheduleRepository implements ScheduleRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function save(Schedule $schedule): void
    {
        $entity = $this->entityManager->find(ScheduleDoctrineEntity::class, $schedule->id()->value());

        if ($entity === null) {
            if ($schedule instanceof DailySchedule) {
                $times = array_map(static function (TimeOfDay $t) {
                    return ['hour' => $t->hour(), 'minute' => $t->minute()];
                }, $schedule->timesOfDay());

                $entity = new DailyScheduleDoctrineEntity(
                    $schedule->id()->value(),
                    $schedule->medicationId()->value(),
                    $times,
                    $schedule->startDate(),
                    $schedule->endDate(),
                    $schedule->clientId(),
                    $schedule->createdAt(),
                    $schedule->updatedAt(),
                    $schedule->dose()?->amount(),
                    $schedule->dose()?->unit()->value
                );
            } elseif ($schedule instanceof DailyIntervalSchedule) {
                $entity = new DailyIntervalScheduleDoctrineEntity(
                    $schedule->id()->value(),
                    $schedule->medicationId()->value(),
                    $schedule->everyHours(),
                    $schedule->startAt()->toString(),
                    $schedule->endAt()?->toString(),
                    $schedule->startDate(),
                    $schedule->endDate(),
                    $schedule->clientId(),
                    $schedule->createdAt(),
                    $schedule->updatedAt(),
                    $schedule->dose()?->amount(),
                    $schedule->dose()?->unit()->value
                );
            } elseif ($schedule instanceof SpecificDaysSchedule) {
                $times = array_map(static function (TimeOfDay $t) {
                    return ['hour' => $t->hour(), 'minute' => $t->minute()];
                }, $schedule->timesOfDay());

                $entity = new SpecificDaysScheduleDoctrineEntity(
                    $schedule->id()->value(),
                    $schedule->medicationId()->value(),
                    $schedule->daysOfWeek(),
                    $times,
                    $schedule->startDate(),
                    $schedule->endDate(),
                    $schedule->clientId(),
                    $schedule->createdAt(),
                    $schedule->updatedAt(),
                    $schedule->dose()?->amount(),
                    $schedule->dose()?->unit()->value
                );
            } else {
                throw new \InvalidArgumentException('Unknown schedule class: ' . get_class($schedule));
            }

            $this->entityManager->persist($entity);
        } else {
            if ($schedule instanceof DailySchedule && $entity instanceof DailyScheduleDoctrineEntity) {
                $times = array_map(static function (TimeOfDay $t) {
                    return ['hour' => $t->hour(), 'minute' => $t->minute()];
                }, $schedule->timesOfDay());
                $entity->setTimesOfDay($times);
            }
            $entity->setDoseAmount($schedule->dose()?->amount());
            $entity->setDoseUnit($schedule->dose()?->unit()->value);
            $entity->setUpdatedAt($schedule->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function findById(ScheduleId $id): ?Schedule
    {
        $entity = $this->entityManager->find(ScheduleDoctrineEntity::class, $id->value());
        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    /**
     * @return Schedule[]
     */
    public function findByMedicationId(MedicationId $medicationId): array
    {
        $entities = $this->entityManager->getRepository(ScheduleDoctrineEntity::class)
            ->findBy(['medicationId' => $medicationId->value()]);

        return array_map($this->mapToDomain(...), $entities);
    }

    /**
     * @param MedicationId[] $medicationIds
     * @return Schedule[]
     */
    public function findByMedicationIds(array $medicationIds): array
    {
        if (count($medicationIds) === 0) {
            return [];
        }

        $ids = array_map(static fn (MedicationId $id) => $id->value(), $medicationIds);

        $entities = $this->entityManager->getRepository(ScheduleDoctrineEntity::class)
            ->findBy(['medicationId' => $ids]);

        return array_map($this->mapToDomain(...), $entities);
    }

    public function findByClientId(string $clientId): ?Schedule
    {
        $entity = $this->entityManager->getRepository(ScheduleDoctrineEntity::class)
            ->findOneBy(['clientId' => $clientId]);

        if ($entity === null) {
            return null;
        }

        return $this->mapToDomain($entity);
    }

    public function delete(Schedule $schedule): void
    {
        $entity = $this->entityManager->find(ScheduleDoctrineEntity::class, $schedule->id()->value());
        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    private function mapToDomain(ScheduleDoctrineEntity $entity): Schedule
    {
        $id = new ScheduleId($entity->getId());
        $medicationId = new MedicationId($entity->getMedicationId());
        $dose = Dose::tryFromStorage($entity->getDoseAmount(), $entity->getDoseUnit());

        if ($entity instanceof DailyScheduleDoctrineEntity) {
            $times = array_map(static function (array $t) {
                return new TimeOfDay($t['hour'], $t['minute']);
            }, $entity->getTimesOfDay());

            return new DailySchedule(
                $id,
                $medicationId,
                $times,
                $entity->getStartDate(),
                $entity->getEndDate(),
                $entity->getClientId(),
                $entity->getCreatedAt(),
                $entity->getUpdatedAt(),
                $dose
            );
        }

        if ($entity instanceof DailyIntervalScheduleDoctrineEntity) {
            $everyHours = $entity->getEveryHours();
            $startAt = TimeOfDay::fromString($entity->getStartAt());
            $endAt = $entity->getEndAt() !== null ? TimeOfDay::fromString($entity->getEndAt()) : null;

            return new DailyIntervalSchedule(
                $id,
                $medicationId,
                $everyHours,
                $startAt,
                $endAt,
                $entity->getStartDate(),
                $entity->getEndDate(),
                $entity->getClientId(),
                $entity->getCreatedAt(),
                $entity->getUpdatedAt(),
                $dose
            );
        }

        if ($entity instanceof SpecificDaysScheduleDoctrineEntity) {
            $days = $entity->getDaysOfWeek();
            $times = array_map(static function (array $t) {
                return new TimeOfDay($t['hour'], $t['minute']);
            }, $entity->getTimesOfDay());

            return new SpecificDaysSchedule(
                $id,
                $medicationId,
                $days,
                $times,
                $entity->getStartDate(),
                $entity->getEndDate(),
                $entity->getClientId(),
                $entity->getCreatedAt(),
                $entity->getUpdatedAt(),
                $dose
            );
        }

        throw new \LogicException('Unknown entity class: ' . get_class($entity));
    }
}
