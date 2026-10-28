<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BloodType;
use App\Enums\PatientHistoryType;
use App\Enums\Sex;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $folio
 * @property string $first_name
 * @property string $last_name
 * @property string|null $second_last_name
 * @property Carbon|null $birth_date
 * @property Sex|null $sex
 * @property string|null $curp
 * @property string|null $curp_hash
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $occupation
 * @property BloodType|null $blood_type
 * @property string|null $emergency_name
 * @property string|null $emergency_phone
 * @property string|null $emergency_relationship
 * @property string|null $notes
 * @property-read string $full_name
 * @property-read int|null $age
 */
#[Fillable([
    'folio', 'first_name', 'last_name', 'second_last_name', 'birth_date', 'sex', 'curp',
    'phone', 'email', 'address', 'occupation', 'blood_type',
    'emergency_name', 'emergency_phone', 'emergency_relationship', 'notes',
])]
#[Hidden(['curp_hash'])]
class Patient extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $appends = ['full_name', 'age'];

    protected static function booted(): void
    {
        static::saving(function (Patient $patient): void {
            if ($patient->isDirty('curp')) {
                $patient->curp_hash = self::hashCurp($patient->curp);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'sex' => Sex::class,
            'blood_type' => BloodType::class,
            'curp' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    /**
     * HMAC de la CURP normalizada, para buscar sin descifrar.
     */
    public static function hashCurp(?string $curp): ?string
    {
        $normalized = strtoupper(trim((string) $curp));

        if ($normalized === '') {
            return null;
        }

        return hash_hmac('sha256', $normalized, (string) config('medical.blind_index_key'));
    }

    /**
     * Búsqueda rápida por nombre, teléfono, folio o CURP exacta.
     *
     * @param  Builder<Patient>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $curpHash = strlen($term) === 18 ? self::hashCurp($term) : null;

        $query->where(function (Builder $q) use ($term, $curpHash): void {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $q->where('folio', $term)
                ->orWhere('phone', 'like', $like)
                ->orWhereRaw("CONCAT_WS(' ', first_name, last_name, second_last_name) LIKE ?", [$like]);

            if ($curpHash !== null) {
                $q->orWhere('curp_hash', $curpHash);
            }
        });
    }

    /** @return Attribute<string, never> */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
            $this->second_last_name,
        ]))));
    }

    /** @return Attribute<int|null, never> */
    protected function age(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->birth_date !== null
            ? (int) $this->birth_date->diffInYears(now())
            : null);
    }

    /** @return HasMany<PatientHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(PatientHistory::class);
    }

    /** @return HasMany<PatientHistory, $this> */
    public function allergies(): HasMany
    {
        return $this->hasMany(PatientHistory::class)->where('type', PatientHistoryType::Allergy);
    }

    /** @return HasMany<PatientHistory, $this> */
    public function criticalAllergies(): HasMany
    {
        return $this->allergies()->where('is_critical', true);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** @return HasMany<Consultation, $this> */
    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    /** @return HasMany<MedicalFile, $this> */
    public function medicalFiles(): HasMany
    {
        return $this->hasMany(MedicalFile::class);
    }

    /** @return HasMany<Charge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }
}
