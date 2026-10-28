<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MedicalFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Metadatos del estudio (nunca la ruta física).
 *
 * @mixin MedicalFile
 */
class MedicalFileResource extends JsonResource
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
            'title' => $this->title,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'is_image' => $this->isImage(),
            'is_pdf' => $this->mime_type === 'application/pdf',
            'size_bytes' => $this->size_bytes,
            'study_date' => $this->study_date?->toDateString(),
            'notes' => $this->notes,
            'consultation_id' => $this->consultation_id,
            'uploaded_by' => $this->uploader?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
