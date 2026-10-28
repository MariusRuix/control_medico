<?php

declare(strict_types=1);

namespace App\Http\Requests\Patients;

use App\Enums\BloodType;
use App\Enums\Sex;
use App\Models\Patient;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de la ficha de identificación. La autorización se aplica
 * en la ruta con el middleware de permisos.
 */
class PatientRequest extends FormRequest
{
    private const string CURP_REGEX = '/^[A-Z][AEIOUX][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HMX](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z\d]\d$/';

    private const string PHONE_REGEX = '/^[0-9+()\s-]{7,20}$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('curp'))) {
            $this->merge(['curp' => strtoupper(trim($this->input('curp')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'second_last_name' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'after:1900-01-01', 'before_or_equal:today'],
            'sex' => ['nullable', Rule::enum(Sex::class)],
            'curp' => ['nullable', 'string', 'size:18', 'regex:'.self::CURP_REGEX, $this->uniqueCurp()],
            'phone' => ['nullable', 'string', 'regex:'.self::PHONE_REGEX],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'blood_type' => ['nullable', Rule::enum(BloodType::class)],
            'emergency_name' => ['nullable', 'string', 'max:150'],
            'emergency_phone' => ['nullable', 'string', 'regex:'.self::PHONE_REGEX],
            'emergency_relationship' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellido paterno',
            'second_last_name' => 'apellido materno',
            'birth_date' => 'fecha de nacimiento',
            'sex' => 'sexo',
            'curp' => 'CURP',
            'phone' => 'teléfono',
            'email' => 'correo electrónico',
            'address' => 'domicilio',
            'occupation' => 'ocupación',
            'blood_type' => 'tipo de sangre',
            'emergency_name' => 'contacto de emergencia',
            'emergency_phone' => 'teléfono de emergencia',
            'emergency_relationship' => 'parentesco',
            'notes' => 'notas',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'curp.regex' => 'La CURP no tiene un formato válido.',
            'phone.regex' => 'El teléfono solo puede contener números, espacios, +, ( ) y guiones.',
            'emergency_phone.regex' => 'El teléfono solo puede contener números, espacios, +, ( ) y guiones.',
        ];
    }

    /**
     * La CURP está cifrada, así que la unicidad se valida contra su HMAC.
     */
    private function uniqueCurp(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $hash = Patient::hashCurp(is_string($value) ? $value : null);

            if ($hash === null) {
                return;
            }

            /** @var Patient|null $current */
            $current = $this->route('patient');

            $exists = Patient::withTrashed()
                ->where('curp_hash', $hash)
                ->when($current !== null, fn ($q) => $q->whereKeyNot($current->id))
                ->first(['id', 'folio']);

            if ($exists !== null) {
                $fail("Ya existe un paciente con esta CURP (folio {$exists->folio}).");
            }
        };
    }
}
