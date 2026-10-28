<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PatientHistoryType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Antecedentes y alergias del paciente.
 *
 * @property int $id
 * @property int $patient_id
 * @property PatientHistoryType $type
 * @property string $description
 * @property string|null $relative
 * @property bool $is_critical
 * @property int|null $created_by
 */
#[Fillable(['patient_id', 'type', 'description', 'relative', 'is_critical', 'created_by'])]
class PatientHistory extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => PatientHistoryType::class,
            'description' => 'encrypted',
            'is_critical' => 'boolean',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
