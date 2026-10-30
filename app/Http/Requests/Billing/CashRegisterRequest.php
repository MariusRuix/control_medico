<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Apertura (opening_amount) o corte (counted_cash + notes) de caja.
 */
class CashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->routeIs('cash.open')
            ? ['opening_amount' => ['required', 'numeric', 'min:0', 'max:999999.99']]
            : [
                'counted_cash' => ['required', 'numeric', 'min:0', 'max:999999.99'],
                'notes' => ['nullable', 'string', 'max:500'],
            ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'opening_amount' => 'fondo inicial',
            'counted_cash' => 'efectivo contado',
            'notes' => 'notas',
        ];
    }
}
