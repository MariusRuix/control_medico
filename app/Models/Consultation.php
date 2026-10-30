<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Consulta: signos vitales + nota de evolución SOAP.
 *
 * @property int $id
 * @property int $patient_id
 * @property int|null $appointment_id
 * @property int|null $note_template_id
 * @property Carbon $consulted_at
 * @property string|null $weight_kg
 * @property string|null $height_cm
 * @property string|null $bmi
 * @property int|null $systolic_bp
 * @property int|null $diastolic_bp
 * @property int|null $heart_rate
 * @property int|null $respiratory_rate
 * @property string|null $temperature_c
 * @property int|null $oxygen_saturation
 * @property int|null $glucose_mg_dl
 * @property string|null $reason
 * @property string|null $subjective
 * @property string|null $objective
 * @property string|null $assessment
 * @property string|null $plan
 * @property Carbon|null $signed_at
 * @property int|null $created_by
 */
#[Fillable([
    'patient_id', 'appointment_id', 'note_template_id', 'consulted_at',
    'weight_kg', 'height_cm', 'bmi', 'systolic_bp', 'diastolic_bp', 'heart_rate',
    'respiratory_rate', 'temperature_c', 'oxygen_saturation', 'glucose_mg_dl',
    'reason', 'subjective', 'objective', 'assessment', 'plan', 'signed_at', 'created_by',
])]
class Consultation extends Model
{
    use Auditable, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Consultation $consultation): void {
            $consultation->bmi = self::calculateBmi($consultation->weight_kg, $consultation->height_cm);
        });
    }

    protected function casts(): array
    {
        return [
            'consulted_at' => 'datetime',
            'signed_at' => 'datetime',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:1',
            'bmi' => 'decimal:2',
            'temperature_c' => 'decimal:1',
            'systolic_bp' => 'integer',
            'diastolic_bp' => 'integer',
            'heart_rate' => 'integer',
            'respiratory_rate' => 'integer',
            'oxygen_saturation' => 'integer',
            'glucose_mg_dl' => 'integer',
            'reason' => 'encrypted',
            'subjective' => 'encrypted',
            'objective' => 'encrypted',
            'assessment' => 'encrypted',
            'plan' => 'encrypted',
        ];
    }

    public static function calculateBmi(string|float|null $weightKg, string|float|null $heightCm): ?string
    {
        $weight = (float) $weightKg;
        $height = (float) $heightCm / 100;

        if ($weight <= 0 || $height <= 0) {
            return null;
        }

        return number_format($weight / ($height ** 2), 2, '.', '');
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<NoteTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NoteTemplate::class, 'note_template_id');
    }

    /** @return HasMany<ConsultationDiagnosis, $this> */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(ConsultationDiagnosis::class);
    }

    /** @return HasMany<ConsultationAddendum, $this> */
    public function addenda(): HasMany
    {
        return $this->hasMany(ConsultationAddendum::class)->oldest();
    }

    /** @return HasMany<Prescription, $this> */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    /** @return HasMany<MedicalFile, $this> */
    public function medicalFiles(): HasMany
    {
        return $this->hasMany(MedicalFile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
