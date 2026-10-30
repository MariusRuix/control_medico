<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Models\Medication;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_favorite' => $this->boolean('is_favorite'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'generic_name' => ['required', 'string', 'max:150', $this->uniqueCombination()],
            'brand_name' => ['nullable', 'string', 'max:150'],
            'presentation' => ['nullable', 'string', 'max:150'],
            'concentration' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:50'],
            'is_favorite' => ['boolean'],
            'default_dose' => ['nullable', 'string', 'max:100'],
            'default_frequency' => ['nullable', 'string', 'max:100'],
            'default_duration' => ['nullable', 'string', 'max:100'],
            'default_instructions' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'generic_name' => 'nombre genérico',
            'brand_name' => 'nombre comercial',
            'presentation' => 'presentación',
            'concentration' => 'concentración',
            'route' => 'vía',
            'default_dose' => 'dosis',
            'default_frequency' => 'frecuencia',
            'default_duration' => 'duración',
            'default_instructions' => 'indicaciones',
        ];
    }

    /**
     * Evita duplicar el mismo genérico con la misma concentración y presentación.
     */
    private function uniqueCombination(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            /** @var Medication|null $current */
            $current = $this->route('medication');

            $exists = Medication::query()
                ->where('generic_name', $value)
                ->where('concentration', $this->input('concentration'))
                ->where('presentation', $this->input('presentation'))
                ->when($current !== null, fn ($q) => $q->whereKeyNot($current->id))
                ->exists();

            if ($exists) {
                $fail('Ya existe este medicamento con la misma concentración y presentación.');
            }
        };
    }
}
