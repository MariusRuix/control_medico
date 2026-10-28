<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Models\Appointment;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;

/**
 * Datos del calendario para la vista (día, semana o mes).
 * Las fechas se envían como hora local del consultorio ("Y-m-d\TH:i:s", sin zona).
 */
final class CalendarQuery
{
    public const array VIEWS = ['day', 'week', 'month'];

    private const string LOCAL = 'Y-m-d\TH:i:s';

    public function __construct(private readonly WorkingHours $hours) {}

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public function range(string $view, CarbonImmutable $date): array
    {
        return match ($view) {
            'day' => ['from' => $date->startOfDay(), 'to' => $date->startOfDay()->addDay()],
            'month' => [
                'from' => $date->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY),
                'to' => $date->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->addSecond()->startOfDay(),
            ],
            default => [
                'from' => $date->startOfWeek(CarbonImmutable::MONDAY),
                'to' => $date->startOfWeek(CarbonImmutable::MONDAY)->addWeek(),
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $view, CarbonImmutable $date): array
    {
        ['from' => $from, 'to' => $to] = $this->range($view, $date);

        $appointments = Appointment::query()
            ->with(['patient:id,folio,first_name,last_name,second_last_name,phone', 'service:id,name,color'])
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $a): array => $this->appointment($a))
            ->values()
            ->all();

        $blocks = ScheduleBlock::query()
            ->overlapping($from, $to)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (ScheduleBlock $b): array => [
                'id' => $b->id,
                'starts_at' => $b->starts_at->format(self::LOCAL),
                'ends_at' => $b->ends_at->format(self::LOCAL),
                'reason' => $b->reason,
            ])
            ->values()
            ->all();

        return [
            'view' => $view,
            'date' => $date->toDateString(),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'today' => CarbonImmutable::today()->toDateString(),
            'appointments' => $appointments,
            'blocks' => $blocks,
            'workingHours' => $this->hours->weekly(),
            'slotMinutes' => $this->hours->slotMinutes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function appointment(Appointment $a): array
    {
        return [
            'id' => $a->id,
            'starts_at' => $a->starts_at->format(self::LOCAL),
            'ends_at' => $a->ends_at->format(self::LOCAL),
            'duration_minutes' => (int) $a->starts_at->diffInMinutes($a->ends_at),
            'status' => $a->status->value,
            'reason' => $a->reason,
            'notes' => $a->notes,
            'cancel_reason' => $a->cancel_reason,
            'patient' => $a->patient !== null ? [
                'id' => $a->patient->id,
                'folio' => $a->patient->folio,
                'full_name' => $a->patient->full_name,
                'phone' => $a->patient->phone,
            ] : null,
            'service' => $a->service !== null ? [
                'id' => $a->service->id,
                'name' => $a->service->name,
                'color' => $a->service->color,
            ] : null,
        ];
    }
}
