<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Medication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Medicamento con su posología predeterminada, para el formulario de receta.
 *
 * @mixin Medication
 */
class MedicationOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'generic_name' => $this->generic_name,
            'route' => $this->route,
            'is_favorite' => $this->is_favorite,
            'default_dose' => $this->default_dose,
            'default_frequency' => $this->default_frequency,
            'default_duration' => $this->default_duration,
            'default_instructions' => $this->default_instructions,
        ];
    }
}
