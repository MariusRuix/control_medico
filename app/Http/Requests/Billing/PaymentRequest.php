<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
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
        return [
            'payment' => ['required', 'array'],
            'payment.amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment.reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'payment.amount' => 'monto',
            'payment.method' => 'método de pago',
            'payment.reference' => 'referencia',
        ];
    }
}
