<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
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
     */
    public function log(string $action, ?Model $model = null, array $oldValues = [], array $newValues = []): void
    {
        try {
            AuditLog::query()->create([
                'user_id' => Auth::id(),
                'action' => $action,
                'auditable_type' => $model?->getMorphClass(),
                'auditable_id' => $model?->getKey(),
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
