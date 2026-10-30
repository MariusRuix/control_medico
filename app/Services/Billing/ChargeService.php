<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ChargeService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Crea el cargo con sus conceptos y, opcionalmente, registra el pago inmediato.
     *
     * @param  array{consultation_id?: int|null, notes?: string|null, items: list<array{service_id?: int|null, description: string, quantity: string|int|float, unit_price: string|int|float, discount?: string|int|float|null}>, payment?: array{amount: string, method: string, reference?: string|null}|null}  $data
     */
    public function create(Patient $patient, array $data, User $author): Charge
    {
        try {
            return DB::transaction(function () use ($patient, $data, $author): Charge {
                $charge = $patient->charges()->create([
                    'consultation_id' => $data['consultation_id'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'status' => ChargeStatus::Pending,
                    'created_by' => $author->id,
                ]);

                $total = 0;

                foreach ($data['items'] as $item) {
                    $service = isset($item['service_id']) ? Service::query()->find($item['service_id']) : null;
                    $quantity = (float) $item['quantity'];
                    $unit = Money::toCents($item['unit_price']);
                    $discount = Money::toCents($item['discount'] ?? 0);
                    $subtotal = (int) round($quantity * $unit) - $discount;

                    if ($subtotal < 0) {
                        throw ValidationException::withMessages(['items' => 'El descuento no puede ser mayor que el importe del concepto.']);
                    }

                    $charge->items()->create([
                        'service_id' => $service?->id,
                        'description' => $item['description'],
                        'quantity' => number_format($quantity, 2, '.', ''),
                        'unit_price' => Money::fromCents($unit),
                        'discount' => Money::fromCents($discount),
                        'subtotal' => Money::fromCents($subtotal),
                    ]);

                    $total += $subtotal;
                }

                if ($total <= 0) {
                    throw ValidationException::withMessages(['items' => 'El total del cobro debe ser mayor a cero.']);
                }

                $charge->update([
                    'total' => Money::fromCents($total),
                    'paid' => '0.00',
                    'balance' => Money::fromCents($total),
                ]);

                if (! empty($data['payment'])) {
                    $this->payments->register(
                        $charge,
                        (string) $data['payment']['amount'],
                        (string) $data['payment']['method'],
                        $data['payment']['reference'] ?? null,
                        $author,
                    );
                }

                return $charge->refresh();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('No se pudo registrar el cobro.', ['patient_id' => $patient->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Cancela un cargo sin pagos (para cargos con pagos, primero se cancelan los pagos).
     */
    public function cancel(Charge $charge, string $reason): void
    {
        if ($charge->payments()->exists()) {
            throw ValidationException::withMessages(['reason' => 'El cobro tiene pagos registrados; cancélalos primero.']);
        }

        if ($charge->status === ChargeStatus::Cancelled) {
            return;
        }

        try {
            DB::transaction(function () use ($charge, $reason): void {
                $this->audit->log('cancelled', $charge, [], ['reason' => $reason]);
                $charge->update(['status' => ChargeStatus::Cancelled, 'balance' => '0.00']);
            });
        } catch (Throwable $e) {
            Log::error('No se pudo cancelar el cobro.', ['charge_id' => $charge->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }
}
