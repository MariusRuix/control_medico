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
     * Estatus que ocupan el horario (para validar empalmes).
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::Scheduled, self::Confirmed, self::InProgress, self::Completed];
    }
}
