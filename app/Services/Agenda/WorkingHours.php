<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Horario de atención configurado en settings (schedule.working_hours).
 * Formato: ['mon' => [['09:00', '14:00'], ['16:00', '20:00']], ..., 'sun' => []].
 */
final class WorkingHours
{
    public const array DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    private const int DEFAULT_SLOT_MINUTES = 30;

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array<string, list<array{0: string, 1: string}>>
     */
    public function weekly(): array
    {
        $configured = $this->settings->get('schedule.working_hours', []);
        $weekly = [];

        foreach (self::DAYS as $day) {
            $ranges = is_array($configured) && isset($configured[$day]) && is_array($configured[$day])
                ? $configured[$day]
                : [];

            $weekly[$day] = array_values(array_filter(
                $ranges,
                static fn (mixed $r): bool => is_array($r)
                    && isset($r[0], $r[1])
                    && preg_match('/^\d{2}:\d{2}$/', (string) $r[0]) === 1
                    && preg_match('/^\d{2}:\d{2}$/', (string) $r[1]) === 1
                    && $r[0] < $r[1],
            ));
        }

        return $weekly;
    }

    public function slotMinutes(): int
    {
        $minutes = (int) $this->settings->get('schedule.slot_minutes', self::DEFAULT_SLOT_MINUTES);

        return $minutes >= 5 && $minutes <= 120 ? $minutes : self::DEFAULT_SLOT_MINUTES;
    }

    /**
     * Rangos de atención de una fecha concreta.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function rangesFor(CarbonInterface $date): array
    {
        $day = strtolower($date->format('D'));
        $base = CarbonImmutable::parse($date->format('Y-m-d'), $date->getTimezone());

        return array_map(
            static fn (array $range): array => [
                $base->setTimeFromTimeString($range[0]),
                $base->setTimeFromTimeString($range[1]),
            ],
            $this->weekly()[$day] ?? [],
        );
    }

    /**
     * ¿El intervalo cae completo dentro de un mismo rango de atención?
     */
    public function contains(CarbonInterface $start, CarbonInterface $end): bool
    {
        if (! $start->isSameDay($end) && ! $end->eq($start->copy()->addDay()->startOfDay())) {
            return false;
        }

        foreach ($this->rangesFor($start) as [$from, $to]) {
            if ($start->gte($from) && $end->lte($to)) {
                return true;
            }
        }

        return false;
    }
}
