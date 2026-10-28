<?php

declare(strict_types=1);

namespace App\Http\Requests\Clinical;

use App\Enums\DiagnosisType;
use App\Models\NoteTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

/**
 * Para firmar, la nota debe tener los elementos mínimos de la nota de
 * evolución (NOM-004): evolución, diagnóstico y plan de tratamiento.
 */
class SignConsultationRequest extends ConsultationRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['subjective'] = ['required', 'string', 'max:20000'];
        $rules['plan'] = ['required', 'string', 'max:20000'];
        $rules['diagnoses'] = ['required', 'array', 'min:1', 'max:10'];

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $hasPrincipal = collect($this->input('diagnoses', []))
                    ->contains(fn (mixed $d): bool => is_array($d) && ($d['type'] ?? null) === DiagnosisType::Primary->value);

                if (! $hasPrincipal) {
                    $validator->errors()->add('diagnoses', 'Marca un diagnóstico como principal para firmar.');
                }
            },
            function (Validator $validator): void {
                // Evita firmar una plantilla sin llenar.
                $template = NoteTemplate::query()->find($this->input('note_template_id'));

                foreach (['subjective', 'objective', 'assessment', 'plan'] as $field) {
                    $original = trim((string) ($template?->structure[$field] ?? ''));

                    if ($original !== '' && trim((string) $this->input($field)) === $original) {
                        $validator->errors()->add($field, 'Completa este apartado: aún tiene el texto de la plantilla.');
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subjective.required' => 'Describe la evolución (subjetivo) para firmar.',
            'plan.required' => 'Indica el plan de tratamiento para firmar.',
            'diagnoses.required' => 'Agrega al menos un diagnóstico para firmar.',
            'diagnoses.min' => 'Agrega al menos un diagnóstico para firmar.',
        ];
    }
}
