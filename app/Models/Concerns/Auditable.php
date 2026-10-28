<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Registra automáticamente altas, cambios, bajas y restauraciones en audit_logs.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            app(AuditLogger::class)->log('created', $model, [], $model->getAttributes());
        });

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            app(AuditLogger::class)->log('updated', $model, $original, $changes);
        });

        static::deleted(function (Model $model): void {
            $action = in_array(SoftDeletes::class, class_uses_recursive($model), true)
                && ! $model->isForceDeleting()
                ? 'deleted'
                : 'force_deleted';

            app(AuditLogger::class)->log($action, $model);
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(function (Model $model): void {
                app(AuditLogger::class)->log('restored', $model);
            });
        }
    }
}
