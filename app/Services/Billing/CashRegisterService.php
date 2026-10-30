<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\PaymentMethod;
use App\Models\CashRegister;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Apertura y corte de caja. Solo hay una caja abierta a la vez.
 */
final class CashRegisterService
{
    public function current(): ?CashRegister
    {
        return CashRegister::query()->open()->latest('opened_at')->first();
    }

    public function open(string $openingAmount, User $user): CashRegister
    {
        try {
            return DB::transaction(function () use ($openingAmount, $user): CashRegister {
                if (CashRegister::query()->open()->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['opening_amount' => 'Ya hay una caja abierta.']);
                }

                return CashRegister::query()->create([
                    'opened_by' => $user->id,
                    'opened_at' => now(),
                    'opening_amount' => Money::fromCents(Money::toCents($openingAmount)),
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('No se pudo abrir la caja.', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Corte: calcula lo esperado por método y la diferencia contra el efectivo contado.
     */
    public function close(CashRegister $register, string $countedCash, ?string $notes, User $user): CashRegister
    {
        if (! $register->isOpen()) {
            throw ValidationException::withMessages(['counted_cash' => 'Esta caja ya fue cerrada.']);
        }

        try {
            return DB::transaction(function () use ($register, $countedCash, $notes, $user): CashRegister {
                $register = CashRegister::query()->lockForUpdate()->findOrFail($register->id);
                $totals = $this->totals($register);

                $expectedCash = Money::toCents($register->opening_amount) + $totals[PaymentMethod::Cash->value];
                $counted = Money::toCents($countedCash);

                $register->update([
                    'closed_by' => $user->id,
                    'closed_at' => now(),
                    'expected_cash' => Money::fromCents($expectedCash),
                    'expected_transfer' => Money::fromCents($totals[PaymentMethod::Transfer->value]),
                    'expected_card' => Money::fromCents($totals[PaymentMethod::Card->value]),
                    'counted_cash' => Money::fromCents($counted),
                    'difference' => Money::fromCents($counted - $expectedCash),
                    'notes' => $notes,
                ]);

                return $register;
            });
        } catch (Throwable $e) {
            Log::error('No se pudo cerrar la caja.', ['register_id' => $register->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Totales cobrados (en centavos) por método de pago.
     *
     * @return array<string, int>
     */
    public function totals(CashRegister $register): array
    {
        $totals = array_fill_keys(array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::cases()), 0);

        $register->payments()
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->each(function (mixed $total, string $method) use (&$totals): void {
                $totals[$method] = Money::toCents((string) $total);
            });

        return $totals;
    }

    /**
     * Resumen listo para la vista (montos como cadenas "0.00").
     *
     * @return array<string, mixed>
     */
    public function summary(CashRegister $register): array
    {
        $totals = $this->totals($register);
        $opening = Money::toCents($register->opening_amount);

        return [
            'id' => $register->id,
            'is_open' => $register->isOpen(),
            'opened_at' => $register->opened_at->format('Y-m-d\TH:i:s'),
            'closed_at' => $register->closed_at?->format('Y-m-d\TH:i:s'),
            'opened_by' => $register->opener?->name,
            'closed_by' => $register->closer?->name,
            'opening_amount' => Money::fromCents($opening),
            'totals' => array_map(fn (int $cents): string => Money::fromCents($cents), $totals),
            'total_collected' => Money::fromCents(array_sum($totals)),
            'expected_cash' => $register->isOpen()
                ? Money::fromCents($opening + $totals[PaymentMethod::Cash->value])
                : $register->expected_cash,
            'counted_cash' => $register->counted_cash,
            'difference' => $register->difference,
            'notes' => $register->notes,
        ];
    }
}
