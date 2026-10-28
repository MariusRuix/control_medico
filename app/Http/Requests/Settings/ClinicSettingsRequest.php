<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Datos del consultorio y del médico que aparecen en recetas y documentos.
 */
class ClinicSettingsRequest extends FormRequest
{
    public const array TEXT_FIELDS = [
        'clinic.name', 'clinic.address', 'clinic.phone', 'clinic.email',
        'doctor.name', 'doctor.professional_license', 'doctor.specialty', 'doctor.specialty_license', 'doctor.university',
    ];

    public const array IMAGES = ['clinic.logo_path' => 'logo', 'doctor.signature_path' => 'signature', 'doctor.seal_path' => 'seal'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $image = ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048', 'dimensions:max_width=3000,max_height=3000'];

        return [
            'clinic' => ['required', 'array'],
            'clinic.name' => ['required', 'string', 'max:150'],
            'clinic.address' => ['nullable', 'string', 'max:300'],
            'clinic.phone' => ['nullable', 'string', 'regex:/^[0-9+()\s-]{7,20}$/'],
            'clinic.email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'doctor' => ['required', 'array'],
            'doctor.name' => ['required', 'string', 'max:150'],
            'doctor.professional_license' => ['nullable', 'string', 'regex:/^\d{7,8}$/'],
            'doctor.specialty' => ['nullable', 'string', 'max:150'],
            'doctor.specialty_license' => ['nullable', 'string', 'regex:/^\d{7,8}$/'],
            'doctor.university' => ['nullable', 'string', 'max:200'],
            'logo' => $image,
            'signature' => $image,
            'seal' => $image,
            'remove' => ['nullable', 'array'],
            'remove.*' => ['string', 'in:logo,signature,seal'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'clinic.name' => 'nombre del consultorio',
            'clinic.address' => 'domicilio',
            'clinic.phone' => 'teléfono',
            'clinic.email' => 'correo',
            'doctor.name' => 'nombre del médico',
            'doctor.professional_license' => 'cédula profesional',
            'doctor.specialty' => 'especialidad',
            'doctor.specialty_license' => 'cédula de especialidad',
            'doctor.university' => 'institución',
            'logo' => 'logotipo',
            'signature' => 'firma',
            'seal' => 'sello',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doctor.professional_license.regex' => 'La cédula profesional debe tener 7 u 8 dígitos.',
            'doctor.specialty_license.regex' => 'La cédula de especialidad debe tener 7 u 8 dígitos.',
            'clinic.phone.regex' => 'El teléfono solo puede contener números, espacios, +, ( ) y guiones.',
        ];
    }

    /**
     * @return array<string, string|null> ['clinic.name' => '...', ...]
     */
    public function textValues(): array
    {
        $values = [];

        foreach (self::TEXT_FIELDS as $key) {
            $value = $this->validated($key);
            $values[$key] = is_string($value) && $value !== '' ? $value : null;
        }

        return $values;
    }

    /**
     * @return array<string, UploadedFile> ['doctor.signature_path' => UploadedFile]
     */
    public function uploadedImages(): array
    {
        $files = [];

        foreach (self::IMAGES as $key => $field) {
            $file = $this->file($field);

            if ($file instanceof UploadedFile) {
                $files[$key] = $file;
            }
        }

        return $files;
    }

    /**
     * @return list<string> Llaves de settings de las imágenes a quitar.
     */
    public function imagesToRemove(): array
    {
        $remove = (array) ($this->validated('remove') ?? []);

        return array_values(array_keys(array_filter(
            self::IMAGES,
            static fn (string $field): bool => in_array($field, $remove, true),
        )));
    }
}
