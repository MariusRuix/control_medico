<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\AppointmentStatus;
use App\Enums\Permission;
use App\Models\Appointment;
use App\Models\Charge;
use App\Models\Consultation;
use App\Models\Payment;
use App\Models\User;
use App\Services\Agenda\CalendarQuery;
use App\Services\Billing\CashRegisterService;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Datos de la pantalla de Inicio según los permisos del usuario.
 */
final class DashboardQuery
{
    private const int DRAFTS_LIMIT = 10;

    public function __construct(
        private readonly CalendarQuery $calendar,
        private readonly CashRegisterService $cash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $today = CarbonImmutable::today();

        return [
            'today' => $today->toDateString(),
            'appointments' => $user->can(Permission::AppointmentsView->value) ? $this->appointments($today) : null,
            'stats' => $user->can(Permission::AppointmentsView->value) ? $this->appointmentStats($today) : null,
            'drafts' => $user->can(Permission::ConsultationsManage->value) ? $this->drafts() : null,
            'finance' => $user->can(Permission::ChargesView->value) ? $this->finance($today) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function appointments(CarbonImmutable $today): array
    {
        return Appointment::query()
            ->with(['patient:id,folio,first_name,last_name,second_last_name,phone', 'service:id,name,color', 'consultation:id,appointment_id,signed_at'])
            ->where('starts_at', '>=', $today)
            ->where('starts_at', '<', $today->addDay())
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $a): array => $this->calendar->appointment($a))
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function appointmentStats(CarbonImmutable $today): array
    {
        $counts = Appointment::query()
            ->where('starts_at', '>=', $today)
            ->where('starts_at', '<', $today->addDay())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (AppointmentStatus ...$statuses): int => (int) collect($statuses)
            ->sum(fn (AppointmentStatus $s): int => (int) ($counts[$s->value] ?? 0));

        return [
            'total' => (int) $counts->sum() - $count(AppointmentStatus::Cancelled),
            'pending' => $count(AppointmentStatus::Scheduled, AppointmentStatus::Confirmed),
            'in_progress' => $count(AppointmentStatus::InProgress),
            'completed' => $count(AppointmentStatus::Completed),
            'no_show' => $count(AppointmentStatus::NoShow),
            'tomorrow' => Appointment::query()
                ->whereIn('status', AppointmentStatus::blocking())
                ->where('starts_at', '>=', $today->addDay())
                ->where('starts_at', '<', $today->addDays(2))
                ->count(),
        ];
    }

    /**
     * Notas sin firmar (pendientes de cerrar).
     *
     * @return list<array<string, mixed>>
     */
    private function drafts(): array
    {
        return Consultation::query()
            ->whereNull('signed_at')
            ->whereHas('patient')
            ->with('patient:id,folio,first_name,last_name,second_last_name')
            ->latest('consulted_at')
            ->limit(self::DRAFTS_LIMIT)
            ->get()
            ->map(fn (Consultation $c): array => [
                'id' => $c->id,
                'consulted_at' => $c->consulted_at->format('Y-m-d\TH:i:s'),
                'patient' => $c->patient?->full_name,
                'folio' => $c->patient?->folio,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function finance(CarbonImmutable $today): array
    {
        $collectedToday = (string) Payment::query()
            ->where('paid_at', '>=', $today)
            ->where('paid_at', '<', $today->addDay())
            ->sum('amount');

        $register = $this->cash->current();

        return [
            'collected_today' => Money::fromCents(Money::toCents($collectedToday)),
            'outstanding_total' => Money::fromCents(Money::toCents((string) Charge::query()->outstanding()->sum('balance'))),
            'outstanding_count' => Charge::query()->outstanding()->count(),
            'cash_open' => $register !== null,
            'cash_opened_at' => $register?->opened_at->format('Y-m-d\TH:i:s'),
        ];
    }
}
