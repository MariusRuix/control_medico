<?php

declare(strict_types=1);

namespace App\Http\Requests\MedicalFiles;

use App\Enums\MedicalFileType;
use App\Models\Patient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class UploadMedicalFilesRequest extends FormRequest
{
    public const array MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    private const int MAX_FILES = 10;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'files.*' => [
                'required',
                'file',
                'mimes:'.implode(',', self::MIMES),
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
                'max:'.config('medical.max_upload_kb'),
            ],
            'type' => ['required', Rule::enum(MedicalFileType::class)],
            'title' => ['nullable', 'string', 'max:150'],
            'study_date' => ['nullable', 'date', 'before_or_equal:today'],
            'consultation_id' => [
                'nullable',
                'integer',
                Rule::exists('consultations', 'id')->where('patient_id', $patient->id)->whereNull('deleted_at'),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'files' => 'archivos',
            'files.*' => 'archivo',
            'type' => 'tipo de estudio',
            'title' => 'título',
            'study_date' => 'fecha del estudio',
            'consultation_id' => 'consulta',
            'notes' => 'notas',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mb = (int) round(((int) config('medical.max_upload_kb')) / 1024);

        return [
            'files.required' => 'Selecciona al menos un archivo.',
            'files.max' => 'Puedes subir hasta '.self::MAX_FILES.' archivos a la vez.',
            'files.*.mimes' => 'Solo se aceptan PDF o imágenes (JPG, PNG, WEBP).',
            'files.*.mimetypes' => 'Solo se aceptan PDF o imágenes (JPG, PNG, WEBP).',
            'files.*.max' => "Cada archivo puede pesar hasta {$mb} MB.",
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploadedFiles(): array
    {
        /** @var array<int, UploadedFile> $files */
        $files = $this->file('files', []);

        return array_values($files);
    }

    /**
     * @return array{type: string, title: string|null, study_date: string|null, consultation_id: int|null, notes: string|null}
     */
    public function meta(): array
    {
        $consultationId = $this->validated('consultation_id');

        return [
            'type' => (string) $this->validated('type'),
            'title' => $this->validated('title'),
            'study_date' => $this->validated('study_date'),
            'consultation_id' => $consultationId !== null ? (int) $consultationId : null,
            'notes' => $this->validated('notes'),
        ];
    }
}
