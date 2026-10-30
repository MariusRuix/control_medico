<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\CashRegister;
use App\Models\Charge;
use App\Models\ChargeItem;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consultas de lectura para la pantalla de caja y el expediente.
 */
final class BillingQuery
{
    private const int OUTSTANDING_LIMIT = 50;

    private const int HISTORY_LIMIT = 15;

    /**
     * @return list<array<string, mixed>>
     */
    public function payments(CashRegister $register): array
    {
        return $register->payments()
            ->with(['charge.patient:id,folio,first_name,last_name,second_last_name', 'charge.items:id,charge_id,description', 'receiver:id,name'])
            ->latest('paid_at')
            ->get()
            ->map(fn (Payment $p): array => [
                'id' => $p->id,
                'paid_at' => $p->paid_at->format('Y-m-d\TH:i:s'),
                'amount' => $p->amount,
                'method' => $p->method->value,
                'method_label' => $p->method->label(),
                'reference' => $p->reference,
                'received_by' => $p->receiver?->name,
                'charge_id' => $p->charge_id,
                'patient' => $p->charge?->patient?->full_name,
                'patient_id' => $p->charge?->patient?->id,
                'concept' => $p->charge?->items->pluck('description')->implode(', '),
            ])
            ->values()
            ->all();
    }

    /**
     * Cargos con saldo pendiente (adeudos), más antiguos primero.
     *
     * @return list<array<string, mixed>>
     */
    public function outstanding(?int $patientId = null): array
    {
        return Charge::query()
            ->outstanding()
            ->when($patientId !== null, fn (Builder $q) => $q->where('patient_id', $patientId))
            ->whereHas('patient')
            ->with(['patient:id,folio,first_name,last_name,second_last_name,phone', 'items'])
            ->oldest()
            ->limit(self::OUTSTANDING_LIMIT)
            ->get()
            ->map(fn (Charge $c): array => $this->charge($c))
            ->values()
            ->all();
    }

    /**
     * Últimos cargos del paciente (todos los estatus).
     *
     * @return list<array<string, mixed>>
     */
    public function patientCharges(int $patientId): array
    {
        return Charge::query()
            ->where('patient_id', $patientId)
            ->with(['patient:id,folio,first_name,last_name,second_last_name,phone', 'items'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Charge $c): array => $this->charge($c))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(CashRegisterService $cash): array
    {
        return CashRegister::query()
            ->whereNotNull('closed_at')
            ->with(['opener:id,name', 'closer:id,name'])
            ->latest('closed_at')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->map(fn (CashRegister $r): array => $cash->summary($r))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function charge(Charge $c): array
    {
        return [
            'id' => $c->id,
            'created_at' => $c->created_at?->format('Y-m-d\TH:i:s'),
            'status' => $c->status->value,
            'status_label' => $c->status->label(),
            'total' => $c->total,
            'paid' => $c->paid,
            'balance' => $c->balance,
            'notes' => $c->notes,
            'patient' => $c->patient !== null ? [
                'id' => $c->patient->id,
                'folio' => $c->patient->folio,
                'full_name' => $c->patient->full_name,
                'phone' => $c->patient->phone,
            ] : null,
            'items' => $c->items->map(fn (ChargeItem $i): array => [
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
                'discount' => $i->discount,
                'subtotal' => $i->subtotal,
            ])->values()->all(),
        ];
    }
}
