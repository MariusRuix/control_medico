<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PaymentService
{
    public function __construct(
        private readonly CashRegisterService $cash,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Registra un pago (total o abono) en la caja abierta.
     */
    public function register(Charge $charge, string $amount, string $method, ?string $reference, User $user): Payment
    {
        $register = $this->cash->current();

        if ($register === null) {
            throw ValidationException::withMessages(['payment.amount' => 'Abre la caja antes de registrar pagos.']);
        }

        $cents = Money::toCents($amount);

        if ($cents <= 0) {
            throw ValidationException::withMessages(['payment.amount' => 'El monto debe ser mayor a cero.']);
        }

        try {
            return DB::transaction(function () use ($charge, $cents, $method, $reference, $user, $register): Payment {
                /** @var Charge $charge */
                $charge = Charge::query()->lockForUpdate()->findOrFail($charge->id);

                if ($charge->status === ChargeStatus::Cancelled) {
                    throw ValidationException::withMessages(['payment.amount' => 'El cobro está cancelado.']);
                }

                $balance = Money::toCents($charge->balance);

                if ($cents > $balance) {
                    throw ValidationException::withMessages([
                        'payment.amount' => 'El monto excede el saldo pendiente ($'.Money::fromCents($balance).').',
                    ]);
                }

                $payment = $charge->payments()->create([
                    'cash_register_id' => $register->id,
                    'amount' => Money::fromCents($cents),
                    'method' => PaymentMethod::from($method),
                    'reference' => $reference,
                    'received_by' => $user->id,
                    'paid_at' => now(),
                ]);

                $this->recalculate($charge);

                return $payment;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('No se pudo registrar el pago.', ['charge_id' => $charge->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Cancela un pago capturado por error. Solo mientras su caja siga abierta,
     * para no alterar cortes ya cerrados.
     */
    public function void(Payment $payment, string $reason): void
    {
        $register = $payment->cashRegister;

        if ($register === null || ! $register->isOpen()) {
            throw ValidationException::withMessages(['reason' => 'Solo se pueden cancelar pagos de la caja abierta.']);
        }

        try {
            DB::transaction(function () use ($payment, $reason): void {
                /** @var Charge $charge */
                $charge = Charge::query()->lockForUpdate()->findOrFail($payment->charge_id);

                $this->audit->log('voided', $payment, $payment->only(['amount', 'method', 'reference', 'charge_id']), ['reason' => $reason]);
                $payment->delete();

                $this->recalculate($charge);
            });
        } catch (Throwable $e) {
            Log::error('No se pudo cancelar el pago.', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    private function recalculate(Charge $charge): void
    {
        $paid = Money::toCents((string) $charge->payments()->sum('amount'));
        $total = Money::toCents($charge->total);
        $balance = max(0, $total - $paid);

        $charge->update([
            'paid' => Money::fromCents($paid),
            'balance' => Money::fromCents($balance),
            'status' => match (true) {
                $balance === 0 => ChargeStatus::Paid,
                $paid > 0 => ChargeStatus::Partial,
                default => ChargeStatus::Pending,
            },
        ]);
    }
}
