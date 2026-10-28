<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MedicalFileType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Estudios y archivos adjuntos. Se guardan en un disco privado y se sirven
 * únicamente mediante un controlador que valide permisos.
 *
 * @property int $id
 * @property int $patient_id
 * @property int|null $consultation_id
 * @property MedicalFileType $type
 * @property string $title
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property Carbon|null $study_date
 * @property string|null $notes
 * @property int|null $uploaded_by
 */
#[Fillable([
    'patient_id', 'consultation_id', 'type', 'title', 'disk', 'path', 'original_name',
    'mime_type', 'size_bytes', 'study_date', 'notes', 'uploaded_by',
])]
#[Hidden(['disk', 'path'])]
class MedicalFile extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => MedicalFileType::class,
            'size_bytes' => 'integer',
            'study_date' => 'date',
        ];
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Consultation, $this> */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
