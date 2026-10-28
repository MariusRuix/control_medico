<?php

declare(strict_types=1);

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'agendada';
    case Confirmed = 'confirmada';
    case InProgress = 'en_consultorio';
    case Completed = 'finalizada';
    case Cancelled = 'cancelada';
    case NoShow = 'no_asistio';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendada',
            self::Confirmed => 'Confirmada',
            self::InProgress => 'En consultorio',
            self::Completed => 'Finalizada',
            self::Cancelled => 'Cancelada',
            self::NoShow => 'No asistió',
        };
    }

    /**
     * Color de Tailwind para el calendario.
     */
    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'sky',
            self::Confirmed => 'indigo',
            self::InProgress => 'amber',
            self::Completed => 'emerald',
            self::Cancelled => 'rose',
            self::NoShow => 'zinc',
        };
    }

    /**
     * Cambios de estatus permitidos desde el estatus actual.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::Confirmed, self::InProgress, self::Cancelled, self::NoShow],
            self::Confirmed => [self::Scheduled, self::InProgress, self::Cancelled, self::NoShow],
            self::InProgress => [self::Completed, self::Confirmed],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Una cita cerrada ya no se puede reprogramar.
     */
    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * @return list<array{value: string, label: string, color: string, transitions: list<string>}>
     */
    public static function definitions(): array
    {
        return array_map(static fn (self $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
            'color' => $status->color(),
            'transitions' => array_map(static fn (self $s): string => $s->value, $status->allowedTransitions()),
        ], self::cases());
    }

    /**
     * Estatus que ocupan el horario (para validar empalmes).
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::Scheduled, self::Confirmed, self::InProgress, self::Completed];
    }
}
