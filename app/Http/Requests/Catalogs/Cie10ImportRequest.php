<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class Cie10ImportRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Sube el catálogo en formato CSV (en Excel: Guardar como → CSV UTF-8).',
            'file.mimetypes' => 'Sube el catálogo en formato CSV (en Excel: Guardar como → CSV UTF-8).',
        ];
    }
}
