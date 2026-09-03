<?php

declare(strict_types=1);

namespace Schedule\Application\Command;

use Medication\Domain\MedicationRepository;
use Profile\Domain\ProfileRepository;
use Profile\Domain\ValueObject\Timezone;
use Schedule\Application\ScheduleView;
use Schedule\Domain\DailyIntervalSchedule;
use Schedule\Domain\DailySchedule;
use Schedule\Domain\ScheduleCreatedEvent;
use Schedule\Domain\ScheduleRepository;
use Schedule\Domain\SpecificDaysSchedule;
use Schedule\Domain\ValueObject\TimeOfDay;
use Shared\Application\Bus\EventBus;
use Shared\Domain\Failure;
use Shared\Domain\Result;
use Shared\Domain\ValueObject\Dose;
use Shared\Domain\ValueObject\DoseUnit;
use Shared\Domain\ValueObject\MedicationId;
use Shared\Domain\ValueObject\ProfileId;
use Shared\Domain\ValueObject\ScheduleId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateScheduleHandler
{
    public function __construct(
        private readonly ScheduleRepository $scheduleRepository,
        private readonly MedicationRepository $medicationRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly EventBus $eventBus
    ) {
    }

    /**
     * @return Result<array<string, mixed>>
     */
    public function __invoke(CreateScheduleCommand $command): Result
    {
        $profileId = new ProfileId($command->profileId);
        $profile = $this->profileRepository->findById($profileId);

        if ($profile === null) {
            return Result::failure(Failure::notFound('Profile not found.'));
        }

        if ($profile->accountId()->value() !== $command->accountId) {
            return Result::failure(Failure::forbidden('You do not own this profile.'));
        }

        $medicationId = new MedicationId($command->medicationId);
        $medication = $this->medicationRepository->findById($medicationId);

        if ($medication === null) {
            return Result::failure(Failure::notFound('Medication not found.'));
        }

        if (!$medication->profileId()->equals($profileId)) {
            return Result::failure(Failure::forbidden('Medication does not belong to this profile.'));
        }

        // Idempotency check
        if ($command->clientId !== null && $command->clientId !== '') {
            $existing = $this->scheduleRepository->findByClientId($command->clientId);
            if ($existing !== null) {
                return Result::success(ScheduleView::toArray($existing));
            }
        }

        $doseResult = $this->parseDose($command->doseAmount, $command->doseUnit);
        if ($doseResult->isFailure()) {
            return Result::failure($doseResult->getFailure());
        }
        /** @var Dose $dose */
        $dose = $doseResult->getValue();

        $scheduleId = ScheduleId::generate();
        $now = new \DateTimeImmutable();
        $timezone = Timezone::tryParse($profile->timezone()) ?? new Timezone('UTC');
        $startDate = $timezone->startOfDay($command->startDate);
        $endDate = $command->endDate !== null ? $timezone->endOfDay($command->endDate) : null;

        if ($command->type === 'daily') {
            if ($command->timesOfDay === null || count($command->timesOfDay) === 0) {
                return Result::failure(Failure::validation('timesOfDay is required for daily schedule.'));
            }
            $times = array_map(static function (array $t) {
                return new TimeOfDay($t['hour'], $t['minute']);
            }, $command->timesOfDay);

            $schedule = new DailySchedule(
                $scheduleId,
                $medicationId,
                $times,
                $startDate,
                $endDate,
                $command->clientId,
                $now,
                $now,
                $dose
            );
        } elseif ($command->type === 'daily_interval') {
            if ($command->everyHours === null || $command->everyHours <= 0) {
                return Result::failure(Failure::validation('everyHours must be a positive integer.'));
            }
            if ($command->startAt === null) {
                return Result::failure(Failure::validation('startAt is required for daily_interval schedule.'));
            }
            $startAt = new TimeOfDay($command->startAt['hour'], $command->startAt['minute']);
            $endAt = $command->endAt !== null ? new TimeOfDay($command->endAt['hour'], $command->endAt['minute']) : null;

            $schedule = new DailyIntervalSchedule(
                $scheduleId,
                $medicationId,
                $command->everyHours,
                $startAt,
                $endAt,
                $startDate,
                $endDate,
                $command->clientId,
                $now,
                $now,
                $dose
            );
        } elseif ($command->type === 'specific_days') {
            if ($command->daysOfWeek === null || count($command->daysOfWeek) === 0) {
                return Result::failure(Failure::validation('daysOfWeek is required for specific_days schedule.'));
            }
            if ($command->timesOfDay === null || count($command->timesOfDay) === 0) {
                return Result::failure(Failure::validation('timesOfDay is required for specific_days schedule.'));
            }
            $times = array_map(static function (array $t) {
                return new TimeOfDay($t['hour'], $t['minute']);
            }, $command->timesOfDay);

            $schedule = new SpecificDaysSchedule(
                $scheduleId,
                $medicationId,
                $command->daysOfWeek,
                $times,
                $startDate,
                $endDate,
                $command->clientId,
                $now,
                $now,
                $dose
            );
        } else {
            return Result::failure(Failure::validation('Invalid schedule type.'));
        }

        $this->scheduleRepository->save($schedule);

        // Dispatch domain event for dose event expansion
        $this->eventBus->publish(new ScheduleCreatedEvent(
            $schedule->id()->value(),
            $schedule->medicationId()->value(),
            $profile->id()->value()
        ));

        return Result::success(ScheduleView::toArray($schedule));
    }

    /**
     * @return Result<Dose>
     */
    private function parseDose(string $amount, string $unit): Result
    {
        if ($amount === '' || $unit === '') {
            return Result::failure(Failure::validation(
                'doseAmount and doseUnit are required.',
                ['allowedUnits' => DoseUnit::codes()]
            ));
        }

        try {
            return Result::success(Dose::of($amount, $unit));
        } catch (\ValueError) {
            return Result::failure(Failure::validation(
                'Invalid dose unit.',
                ['allowedUnits' => DoseUnit::codes()]
            ));
        } catch (\InvalidArgumentException $exception) {
            return Result::failure(Failure::validation($exception->getMessage()));
        }
    }
}
