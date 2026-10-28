<?php

declare(strict_types=1);

namespace App\Http\Requests\Patients;

use App\Enums\PatientHistoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatientHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_critical' => $this->boolean('is_critical')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PatientHistoryType::class)],
            'description' => ['required', 'string', 'max:2000'],
            'relative' => ['nullable', 'string', 'max:50'],
            'is_critical' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo',
            'description' => 'descripción',
            'relative' => 'parentesco',
            'is_critical' => 'crítico',
        ];
    }
}
