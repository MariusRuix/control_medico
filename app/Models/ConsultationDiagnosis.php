<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiagnosisType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $consultation_id
 * @property int $cie10_code_id
 * @property DiagnosisType $type
 * @property string|null $notes
 */
#[Fillable(['consultation_id', 'cie10_code_id', 'type', 'notes'])]
class ConsultationDiagnosis extends Model
{
    protected $table = 'consultation_diagnoses';

    protected function casts(): array
    {
        return [
            'type' => DiagnosisType::class,
        ];
    }

    /** @return BelongsTo<Consultation, $this> */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /** @return BelongsTo<Cie10Code, $this> */
    public function code(): BelongsTo
    {
        return $this->belongsTo(Cie10Code::class, 'cie10_code_id');
    }
}
