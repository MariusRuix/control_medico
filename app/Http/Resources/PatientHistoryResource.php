<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PatientHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PatientHistory
 */
class PatientHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'description' => $this->description,
            'relative' => $this->relative,
            'is_critical' => $this->is_critical,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
