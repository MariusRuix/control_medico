<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ConsultationAddendum;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bitácora de accesos y cambios al expediente (NOM-004-SSA3-2012 / NOM-024-SSA3-2012).
 *
 * Los valores de columnas cifradas nunca se copian a la bitácora: solo se
 * registra que cambiaron.
 */
final class AuditLogger
{
    public const string MASK = '[protegido]';

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  User|null  $actor  quién hizo la acción cuando aún no hay sesión (p. ej. al iniciar sesión)
     */
    public function log(string $action, ?Model $model = null, array $oldValues = [], array $newValues = [], ?User $actor = null): void
    {
        try {
            AuditLog::query()->create([
                'user_id' => $actor?->getKey() ?? Auth::id(),
                'action' => $action,
                'auditable_type' => $model?->getMorphClass(),
                'auditable_id' => $model?->getKey(),
                'patient_id' => $this->patientIdFor($model),
                'old_values' => $model !== null ? $this->sanitize($model, $oldValues) : $oldValues,
                'new_values' => $model !== null ? $this->sanitize($model, $newValues) : $newValues,
                'ip_address' => Request::ip(),
                'user_agent' => Str::limit((string) Request::userAgent(), 490, ''),
            ]);
        } catch (Throwable $e) {
            // La bitácora nunca debe tirar la operación principal, pero sí debe quedar rastro.
            Log::error('No se pudo registrar la bitácora de auditoría.', [
                'action' => $action,
                'model' => $model !== null ? $model::class : null,
                'id' => $model?->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Registra la consulta (lectura) de un expediente.
     */
    public function viewed(Model $model): void
    {
        $this->log('viewed', $model);
    }

    /**
     * Paciente al que pertenece el registro, para filtrar la bitácora por expediente.
     */
    private function patientIdFor(?Model $model): ?int
    {
        if ($model === null) {
            return null;
        }

        $id = match (true) {
            $model instanceof Patient => $model->getKey(),
            $model instanceof Prescription, $model instanceof ConsultationAddendum => DB::table('consultations')
                ->where('id', $model->getAttribute('consultation_id'))
                ->value('patient_id'),
            $model instanceof Payment => DB::table('charges')
                ->where('id', $model->getAttribute('charge_id'))
                ->value('patient_id'),
            array_key_exists('patient_id', $model->getAttributes()) => $model->getAttribute('patient_id'),
            default => null,
        };

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(Model $model, array $values): ?array
    {
        if ($values === []) {
            return null;
        }

        $protected = array_merge($model->getHidden(), $this->encryptedAttributes($model));

        foreach ($values as $key => $value) {
            if (in_array($key, $protected, true)) {
                $values[$key] = self::MASK;
            }
        }

        unset($values['updated_at'], $values['created_at']);

        return $values === [] ? null : $values;
    }

    /**
     * @return list<string>
     */
    private function encryptedAttributes(Model $model): array
    {
        $attributes = [];

        foreach ($model->getCasts() as $attribute => $cast) {
            if (is_string($cast) && str_starts_with($cast, 'encrypted')) {
                $attributes[] = $attribute;
            }
        }

        if (method_exists($model, 'auditExclude')) {
            /** @var list<string> $extra */
            $extra = $model->auditExclude();
            $attributes = array_merge($attributes, $extra);
        }

        return $attributes;
    }
}
