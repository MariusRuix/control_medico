<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\PaymentMethod;
use App\Models\Service;

/**
 * Catálogos que necesita el diálogo de cobro (servicios con precio,
 * métodos de pago y si hay caja abierta).
 */
final class BillingOptions
{
    public function __construct(private readonly CashRegisterService $cash) {}

    /**
     * @return array{services: list<array{id: int, name: string, price: string}>, paymentMethods: list<array{value: string, label: string}>, cashOpen: bool}
     */
    public function toArray(): array
    {
        return [
            'services' => Service::query()->active()->orderBy('name')->get(['id', 'name', 'price'])
                ->map(fn (Service $s): array => ['id' => $s->id, 'name' => $s->name, 'price' => (string) $s->price])
                ->values()
                ->all(),
            'paymentMethods' => PaymentMethod::options(),
            'cashOpen' => $this->cash->current() !== null,
        ];
    }
}
