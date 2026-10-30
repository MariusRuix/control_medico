<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class NoteTemplateRequest extends FormRequest
{
    public const array FIELDS = ['reason', 'subjective', 'objective', 'assessment', 'plan'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->has('is_active') ? $this->boolean('is_active') : true]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150', Rule::unique('note_templates', 'name')->ignore($this->route('template'))],
            'is_active' => ['boolean'],
            'structure' => ['required', 'array'],
        ];

        foreach (self::FIELDS as $field) {
            $rules["structure.{$field}"] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $structure = (array) $this->input('structure', []);
                $filled = array_filter(self::FIELDS, fn (string $f): bool => trim((string) ($structure[$f] ?? '')) !== '');

                if ($filled === []) {
                    $validator->errors()->add('structure', 'Escribe el texto de al menos un apartado.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre'];
    }

    /**
     * @return array{name: string, is_active: bool, structure: array<string, string>}
     */
    public function templateData(): array
    {
        $structure = [];

        foreach (self::FIELDS as $field) {
            $value = trim((string) $this->input("structure.{$field}", ''));

            if ($value !== '') {
                $structure[$field] = $value;
            }
        }

        return [
            'name' => (string) $this->validated('name'),
            'is_active' => (bool) $this->validated('is_active'),
            'structure' => $structure,
        ];
    }
}
