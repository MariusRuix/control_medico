<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public const array COLORS = ['sky', 'indigo', 'teal', 'emerald', 'amber', 'rose', 'violet', 'zinc'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_appointable' => $this->boolean('is_appointable'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('services', 'name')->ignore($this->route('service'))],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'is_appointable' => ['boolean'],
            'duration_minutes' => ['nullable', 'required_if:is_appointable,true', 'integer', 'min:5', 'max:480'],
            'color' => ['nullable', Rule::in(self::COLORS)],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'price' => 'precio',
            'duration_minutes' => 'duración',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['duration_minutes.required_if' => 'Indica la duración para poder agendarlo.'];
    }
}
