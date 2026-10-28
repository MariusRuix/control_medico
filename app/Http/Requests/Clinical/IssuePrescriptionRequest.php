<?php

declare(strict_types=1);

namespace App\Http\Requests\Clinical;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssuePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (is_array($items)) {
            $this->merge(['items' => array_map(
                static fn (mixed $item): mixed => is_array($item)
                    ? [...$item, 'save_to_vademecum' => filter_var($item['save_to_vademecum'] ?? false, FILTER_VALIDATE_BOOL)]
                    : $item,
                $items,
            )]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'general_instructions' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:15'],
            'items.*.medication_id' => ['nullable', 'integer', Rule::exists('medications', 'id')],
            'items.*.medication_name' => ['required', 'string', 'max:255'],
            'items.*.dose' => ['required', 'string', 'max:100'],
            'items.*.route' => ['nullable', 'string', 'max:50'],
            'items.*.frequency' => ['required', 'string', 'max:100'],
            'items.*.duration' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['nullable', 'string', 'max:50'],
            'items.*.instructions' => ['nullable', 'string', 'max:500'],
            'items.*.save_to_vademecum' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items' => 'medicamentos',
            'items.*.medication_name' => 'medicamento',
            'items.*.dose' => 'dosis',
            'items.*.route' => 'vía',
            'items.*.frequency' => 'frecuencia',
            'items.*.duration' => 'duración',
            'items.*.quantity' => 'cantidad',
            'items.*.instructions' => 'indicaciones',
            'general_instructions' => 'indicaciones generales',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Agrega al menos un medicamento.',
            'items.min' => 'Agrega al menos un medicamento.',
        ];
    }

    /**
     * @return array{general_instructions: string|null, items: list<array<string, mixed>>}
     */
    public function prescriptionData(): array
    {
        /** @var array{general_instructions?: string|null, items: list<array<string, mixed>>} $validated */
        $validated = $this->validated();

        return [
            'general_instructions' => $validated['general_instructions'] ?? null,
            'items' => array_values($validated['items']),
        ];
    }
}
