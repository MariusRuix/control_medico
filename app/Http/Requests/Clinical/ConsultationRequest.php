<?php

declare(strict_types=1);

namespace App\Http\Requests\Clinical;

use App\Enums\DiagnosisType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Guardado del borrador de la nota. Todo es opcional, pero con rangos
 * fisiológicos para detectar errores de captura (p. ej. 1750 en vez de 175).
 */
class ConsultationRequest extends FormRequest
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
            'note_template_id' => ['nullable', 'integer', Rule::exists('note_templates', 'id')],

            'weight_kg' => ['nullable', 'numeric', 'between:0.5,400'],
            'height_cm' => ['nullable', 'numeric', 'between:20,250'],
            'systolic_bp' => ['nullable', 'integer', 'between:50,260', 'required_with:diastolic_bp'],
            'diastolic_bp' => ['nullable', 'integer', 'between:30,160', 'required_with:systolic_bp', 'lt:systolic_bp'],
            'heart_rate' => ['nullable', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'temperature_c' => ['nullable', 'numeric', 'between:30,45'],
            'oxygen_saturation' => ['nullable', 'integer', 'between:50,100'],
            'glucose_mg_dl' => ['nullable', 'integer', 'between:20,800'],

            'reason' => ['nullable', 'string', 'max:1000'],
            'subjective' => ['nullable', 'string', 'max:20000'],
            'objective' => ['nullable', 'string', 'max:20000'],
            'assessment' => ['nullable', 'string', 'max:20000'],
            'plan' => ['nullable', 'string', 'max:20000'],

            'diagnoses' => ['present', 'array', 'max:10'],
            'diagnoses.*.cie10_code_id' => ['required', 'integer', 'distinct', Rule::exists('cie10_codes', 'id')->where('is_active', true)],
            'diagnoses.*.type' => ['required', Rule::enum(DiagnosisType::class)],
            'diagnoses.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $principal = collect($this->input('diagnoses', []))
                    ->filter(fn (mixed $d): bool => is_array($d) && ($d['type'] ?? null) === DiagnosisType::Primary->value)
                    ->count();

                if ($principal > 1) {
                    $validator->errors()->add('diagnoses', 'Solo puede haber un diagnóstico principal.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'weight_kg' => 'peso',
            'height_cm' => 'talla',
            'systolic_bp' => 'presión sistólica',
            'diastolic_bp' => 'presión diastólica',
            'heart_rate' => 'frecuencia cardiaca',
            'respiratory_rate' => 'frecuencia respiratoria',
            'temperature_c' => 'temperatura',
            'oxygen_saturation' => 'saturación de oxígeno',
            'glucose_mg_dl' => 'glucosa',
            'reason' => 'motivo de consulta',
            'subjective' => 'subjetivo',
            'objective' => 'objetivo',
            'assessment' => 'análisis',
            'plan' => 'plan',
            'diagnoses' => 'diagnósticos',
            'diagnoses.*.cie10_code_id' => 'diagnóstico',
            'diagnoses.*.type' => 'tipo de diagnóstico',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function noteData(): array
    {
        return $this->validated();
    }
}
