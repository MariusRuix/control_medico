<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use App\Models\Charge;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;

/**
 * Reporte financiero del consultorio. Los importes se suman en centavos y
 * se devuelven como texto con dos decimales (igual que en la caja).
 *
 * - Ingresos: pagos recibidos en el periodo (lo que entró a caja).
 * - Facturado: cargos no cancelados creados en el periodo (lo que se cobró, pagado o no).
 * - Adeudos: saldos pendientes a hoy, por antigüedad del cargo.
 */
final class FinancialReport
{
    /** @var array<string, array{0: int, 1: int|null, 2: string}> clave => [desde, hasta, etiqueta] (días) */
    public const array AGING_BUCKETS = [
        'current' => [0, 30, '0 a 30 días'],
        'd31' => [31, 60, '31 a 60 días'],
        'd61' => [61, 90, '61 a 90 días'],
        'd90' => [91, null, 'Más de 90 días'],
    ];

    private const int TOP_DEBTORS = 10;

    /**
     * @return array<string, mixed>
     */
    public function build(ReportPeriod $period): array
    {
        $payments = $this->payments($period);
        $incomeCents = (int) $payments->sum('cents');
        $previousCents = (int) $this->payments($period->previous())->sum('cents');
        $billed = $this->billed($period);

        return [
            'summary' => [
                'income' => Money::fromCents($incomeCents),
                'payments_count' => $payments->count(),
                'previous_income' => Money::fromCents($previousCents),
                'change_pct' => $previousCents > 0 ? round(($incomeCents - $previousCents) / $previousCents * 100, 1) : null,
                'billed' => Money::fromCents($billed['total']),
                'charges_count' => $billed['count'],
                'discounts' => Money::fromCents($billed['discounts']),
                'average_ticket' => $billed['count'] > 0 ? Money::fromCents(intdiv($billed['total'], $billed['count'])) : '0.00',
            ],
            'trend' => $this->trend($period, $payments),
            'methods' => $this->byMethod($payments, $incomeCents),
            'services' => $this->byService($period),
            'aging' => $this->aging(),
        ];
    }

    /**
     * @return Collection<int, array{paid_at: CarbonInterface, cents: int, method: string}>
     */
    private function payments(ReportPeriod $period): Collection
    {
        return Payment::query()
            ->whereBetween('paid_at', [$period->from, $period->to])
            ->toBase()
            ->get(['paid_at', 'amount', 'method'])
            ->map(fn (object $p): array => [
                'paid_at' => Date::parse((string) $p->paid_at),
                'cents' => Money::toCents((string) $p->amount),
                'method' => (string) $p->method,
            ]);
    }

    /**
     * @return array{total: int, count: int, discounts: int}
     */
    private function billed(ReportPeriod $period): array
    {
        $charges = $this->billedCharges($period);

        $totals = (clone $charges)->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as total')->first();

        $discounts = DB::table('charge_items')
            ->whereIn('charge_id', (clone $charges)->select('id'))
            ->sum('discount');

        return [
            'total' => Money::toCents((string) ($totals->total ?? 0)),
            'count' => (int) ($totals->n ?? 0),
            'discounts' => Money::toCents((string) $discounts),
        ];
    }

    /**
     * @return Builder<Charge>
     */
    private function billedCharges(ReportPeriod $period): Builder
    {
        return Charge::query()
            ->where('status', '!=', ChargeStatus::Cancelled->value)
            ->whereBetween('created_at', [$period->from, $period->to]);
    }

    /**
     * @param  Collection<int, array{paid_at: CarbonInterface, cents: int, method: string}>  $payments
     * @return list<array{key: string, label: string, amount: string, count: int}>
     */
    private function trend(ReportPeriod $period, Collection $payments): array
    {
        $cents = [];
        $counts = [];

        foreach ($payments as $payment) {
            $key = $period->bucketKey($payment['paid_at']);
            $cents[$key] = ($cents[$key] ?? 0) + $payment['cents'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $trend = [];

        foreach ($period->buckets() as $key => $label) {
            $trend[] = [
                'key' => $key,
                'label' => $label,
                'amount' => Money::fromCents($cents[$key] ?? 0),
                'count' => $counts[$key] ?? 0,
            ];
        }

        return $trend;
    }

    /**
     * @param  Collection<int, array{paid_at: CarbonInterface, cents: int, method: string}>  $payments
     * @return list<array{method: string, label: string, amount: string, count: int, share: float}>
     */
    private function byMethod(Collection $payments, int $incomeCents): array
    {
        return array_map(function (PaymentMethod $method) use ($payments, $incomeCents): array {
            $rows = $payments->where('method', $method->value);
            $cents = (int) $rows->sum('cents');

            return [
                'method' => $method->value,
                'label' => $method->label(),
                'amount' => Money::fromCents($cents),
                'count' => $rows->count(),
                'share' => $incomeCents > 0 ? round($cents / $incomeCents * 100, 1) : 0.0,
            ];
        }, PaymentMethod::cases());
    }

    /**
     * Importe facturado por servicio (conceptos libres agrupados aparte).
     *
     * @return list<array{service_id: int|null, name: string, quantity: string, amount: string, share: float}>
     */
    public function byService(ReportPeriod $period): array
    {
        $rows = DB::table('charge_items')
            ->join('charges', 'charges.id', '=', 'charge_items.charge_id')
            ->leftJoin('services', 'services.id', '=', 'charge_items.service_id')
            ->where('charges.status', '!=', ChargeStatus::Cancelled->value)
            ->whereBetween('charges.created_at', [$period->from, $period->to])
            ->groupBy('charge_items.service_id', 'services.name')
            ->selectRaw('charge_items.service_id, services.name, SUM(charge_items.quantity) as quantity, SUM(charge_items.subtotal) as amount')
            ->get();

        $total = (int) $rows->sum(fn (object $r): int => Money::toCents((string) $r->amount));

        return $rows
            ->map(function (object $r) use ($total): array {
                $cents = Money::toCents((string) $r->amount);

                return [
                    'service_id' => $r->service_id !== null ? (int) $r->service_id : null,
                    'name' => $r->service_id !== null ? (string) ($r->name ?? 'Servicio eliminado') : 'Otros conceptos',
                    'quantity' => rtrim(rtrim(number_format((float) $r->quantity, 2, '.', ''), '0'), '.'),
                    'amount' => Money::fromCents($cents),
                    'cents' => $cents,
                    'share' => $total > 0 ? round($cents / $total * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('cents')
            ->map(fn (array $r): array => array_diff_key($r, ['cents' => true]))
            ->values()
            ->all();
    }

    /**
     * Saldos pendientes a hoy, por antigüedad del cargo, y pacientes con mayor adeudo.
     *
     * @return array<string, mixed>
     */
    public function aging(): array
    {
        $today = Date::today();
        $buckets = array_map(fn (array $b): array => ['label' => $b[2], 'cents' => 0, 'count' => 0], self::AGING_BUCKETS);
        $debtors = [];

        $charges = Charge::query()
            ->outstanding()
            ->whereHas('patient')
            ->with('patient:id,folio,first_name,last_name,second_last_name,phone')
            ->get(['id', 'patient_id', 'balance', 'created_at']);

        foreach ($charges as $charge) {
            $cents = Money::toCents((string) $charge->balance);
            $age = (int) $charge->created_at?->copy()->startOfDay()->diffInDays($today);

            foreach (self::AGING_BUCKETS as $key => [$min, $max]) {
                if ($age >= $min && ($max === null || $age <= $max)) {
                    $buckets[$key]['cents'] += $cents;
                    $buckets[$key]['count']++;

                    break;
                }
            }

            $debtor = $debtors[$charge->patient_id] ?? [
                'patient_id' => $charge->patient_id,
                'full_name' => $charge->patient?->full_name,
                'folio' => $charge->patient?->folio,
                'phone' => $charge->patient?->phone,
                'cents' => 0,
                'count' => 0,
                'oldest_days' => 0,
            ];
            $debtor['cents'] += $cents;
            $debtor['count']++;
            $debtor['oldest_days'] = max($debtor['oldest_days'], $age);
            $debtors[$charge->patient_id] = $debtor;
        }

        $totalCents = array_sum(array_column($buckets, 'cents'));

        usort($debtors, fn (array $a, array $b): int => $b['cents'] <=> $a['cents']);

        return [
            'total' => Money::fromCents($totalCents),
            'patients_count' => count($debtors),
            'buckets' => array_values(array_map(fn (string $key, array $b): array => [
                'key' => $key,
                'label' => $b['label'],
                'amount' => Money::fromCents($b['cents']),
                'count' => $b['count'],
                'share' => $totalCents > 0 ? round($b['cents'] / $totalCents * 100, 1) : 0.0,
            ], array_keys($buckets), $buckets)),
            'debtors' => array_map(fn (array $d): array => [
                'patient_id' => $d['patient_id'],
                'full_name' => $d['full_name'],
                'folio' => $d['folio'],
                'phone' => $d['phone'],
                'amount' => Money::fromCents($d['cents']),
                'count' => $d['count'],
                'oldest_days' => $d['oldest_days'],
            ], array_slice($debtors, 0, self::TOP_DEBTORS)),
        ];
    }
}
