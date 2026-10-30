<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Periodo de un reporte (fechas completas, zona horaria de la aplicación)
 * y agrupación de la gráfica de ingresos.
 */
final readonly class ReportPeriod
{
    public const array GROUPS = ['day', 'week', 'month'];

    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $to,
        public string $group,
    ) {}

    public static function make(?string $from, ?string $to, ?string $group): self
    {
        $start = $from !== null ? Date::parse($from)->startOfDay() : Date::now()->startOfMonth();
        $end = $to !== null ? Date::parse($to)->endOfDay() : Date::now()->endOfDay();

        return new self($start, $end, $group ?? self::autoGroup($start, $end));
    }

    /**
     * El periodo inmediato anterior con la misma duración (para comparar).
     */
    public function previous(): self
    {
        $days = $this->days();

        return new self(
            $this->from->copy()->subDays($days)->startOfDay(),
            $this->from->copy()->subDay()->endOfDay(),
            $this->group,
        );
    }

    public function days(): int
    {
        return (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;
    }

    /**
     * Clave del grupo al que pertenece una fecha (inicio del día, semana o mes).
     */
    public function bucketKey(CarbonInterface $date): string
    {
        return match ($this->group) {
            'week' => $date->copy()->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
            'month' => $date->copy()->startOfMonth()->toDateString(),
            default => $date->toDateString(),
        };
    }

    /**
     * Todos los grupos del periodo, en orden, con su etiqueta.
     *
     * @return array<string, string> clave => etiqueta
     */
    public function buckets(): array
    {
        $buckets = [];
        $cursor = Date::parse($this->bucketKey($this->from));

        while ($cursor->lte($this->to)) {
            $buckets[$cursor->toDateString()] = match ($this->group) {
                'week' => 'Sem. '.$cursor->translatedFormat('j M'),
                'month' => ucfirst($cursor->translatedFormat('M Y')),
                default => $cursor->translatedFormat('j M'),
            };

            $cursor = match ($this->group) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        return $buckets;
    }

    private static function autoGroup(CarbonInterface $from, CarbonInterface $to): string
    {
        $days = (int) $from->diffInDays($to) + 1;

        return match (true) {
            $days <= 35 => 'day',
            $days <= 120 => 'week',
            default => 'month',
        };
    }
}
