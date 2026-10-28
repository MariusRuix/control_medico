<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila del directorio de pacientes.
 *
 * @mixin Patient
 */
class PatientListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folio' => $this->folio,
            'full_name' => $this->full_name,
            'age' => $this->age,
            'sex' => $this->sex?->value,
            'phone' => $this->phone,
            'email' => $this->email,
            'has_critical_allergies' => (bool) ($this->critical_allergies_exists ?? false),
        ];
    }
}
