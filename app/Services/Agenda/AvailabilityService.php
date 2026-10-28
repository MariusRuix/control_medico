<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Models\Appointment;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Calcula conflictos y horarios libres de la agenda.
 */
final class AvailabilityService
{
    public function __construct(private readonly WorkingHours $hours) {}

    /**
     * Mensaje del primer conflicto encontrado, o null si el horario está libre.
     */
    public function conflict(
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $ignoreAppointmentId = null,
        bool $allowOutsideHours = false,
        bool $lock = false,
    ): ?string {
        if (! $end->gt($start)) {
            return 'La hora de fin debe ser posterior a la de inicio.';
        }

        $block = ScheduleBlock::query()->overlapping($start, $end)->first();

        if ($block !== null) {
            return 'El horario está bloqueado'.($block->reason ? " ({$block->reason})" : '').'.';
        }

        $query = Appointment::query()
            ->overlapping($start, $end, $ignoreAppointmentId)
            ->with('patient:id,first_name,last_name,second_last_name');

        if ($lock) {
            $query->lockForUpdate();
        }

        $appointment = $query->first();

        if ($appointment !== null) {
            $name = $appointment->patient?->full_name ?? 'otro paciente';

            return "Se empalma con la cita de {$name} ({$appointment->starts_at->format('H:i')}–{$appointment->ends_at->format('H:i')}).";
        }

        if (! $allowOutsideHours && ! $this->hours->contains($start, $end)) {
            return 'El horario está fuera del horario de atención.';
        }

        return null;
    }

    /**
     * Horas de inicio libres ("HH:MM") para una fecha y duración.
     *
     * @return list<string>
     */
    public function freeSlots(CarbonInterface $date, int $durationMinutes, ?int $ignoreAppointmentId = null): array
    {
        $dayStart = CarbonImmutable::parse($date->format('Y-m-d'), $date->getTimezone());
        $dayEnd = $dayStart->addDay();
        $step = $this->hours->slotMinutes();
        $now = CarbonImmutable::now($date->getTimezone());

        /** @var Collection<int, array{0: CarbonImmutable, 1: CarbonImmutable}> $busy */
        $busy = Appointment::query()
            ->overlapping($dayStart, $dayEnd, $ignoreAppointmentId)
            ->get(['starts_at', 'ends_at'])
            ->map(fn (Appointment $a): array => [CarbonImmutable::instance($a->starts_at), CarbonImmutable::instance($a->ends_at)])
            ->concat(
                ScheduleBlock::query()->overlapping($dayStart, $dayEnd)->get(['starts_at', 'ends_at'])
                    ->map(fn (ScheduleBlock $b): array => [CarbonImmutable::instance($b->starts_at), CarbonImmutable::instance($b->ends_at)]),
            );

        $slots = [];

        foreach ($this->hours->rangesFor($dayStart) as [$from, $to]) {
            for ($start = $from; $start->addMinutes($durationMinutes)->lte($to); $start = $start->addMinutes($step)) {
                $end = $start->addMinutes($durationMinutes);

                if ($start->lt($now)) {
                    continue;
                }

                $taken = $busy->contains(fn (array $b): bool => $b[0]->lt($end) && $b[1]->gt($start));

                if (! $taken) {
                    $slots[] = $start->format('H:i');
                }
            }
        }

        return $slots;
    }
}
