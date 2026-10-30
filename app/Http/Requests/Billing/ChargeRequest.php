<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChargeRequest extends FormRequest
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
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            'consultation_id' => [
                'nullable',
                'integer',
                Rule::exists('consultations', 'id')->where('patient_id', (int) $this->input('patient_id')),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'pay_now' => ['boolean'],
            // El pago solo se valida si se cobra en el momento.
            'payment' => ['exclude_unless:pay_now,true', 'required', 'array'],
            'payment.amount' => ['exclude_unless:pay_now,true', 'required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment.method' => ['exclude_unless:pay_now,true', 'required', Rule::enum(PaymentMethod::class)],
            'payment.reference' => ['exclude_unless:pay_now,true', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'patient_id' => 'paciente',
            'items' => 'conceptos',
            'items.*.description' => 'concepto',
            'items.*.quantity' => 'cantidad',
            'items.*.unit_price' => 'precio',
            'items.*.discount' => 'descuento',
            'payment.amount' => 'monto',
            'payment.method' => 'método de pago',
            'payment.reference' => 'referencia',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function chargeData(): array
    {
        $validated = $this->validated();

        return [
            'consultation_id' => $validated['consultation_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'items' => array_values($validated['items']),
            'payment' => $this->boolean('pay_now') ? ($validated['payment'] ?? null) : null,
        ];
    }
}
