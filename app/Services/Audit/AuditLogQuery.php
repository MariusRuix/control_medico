<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Consulta filtrada de la bitácora (pantalla y exportación).
 */
final class AuditLogQuery
{
    /**
     * @param  array{patient?: int|null, user?: int|null, action?: string|null, type?: string|null, from?: string|null, to?: string|null}  $filters
     * @return Builder<AuditLog>
     */
    public function filtered(array $filters): Builder
    {
        $type = AuditCatalog::typeClass($filters['type'] ?? null);

        return AuditLog::query()
            ->when($filters['patient'] ?? null, fn (Builder $q, int $id) => $q->where('patient_id', $id))
            ->when($filters['user'] ?? null, fn (Builder $q, int $id) => $q->where('user_id', $id))
            ->when($filters['action'] ?? null, fn (Builder $q, string $action) => $q->where('action', $action))
            ->when($type, fn (Builder $q, string $class) => $q->where('auditable_type', $class))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()));
    }

    /**
     * Cambios registrados como lista campo / antes / después.
     *
     * @return list<array{field: string, old: string|null, new: string|null}>
     */
    public static function changes(AuditLog $log): array
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];
        $changes = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            $changes[] = [
                'field' => (string) $field,
                'old' => self::stringify($old[$field] ?? null),
                'new' => self::stringify($new[$field] ?? null),
            ];
        }

        return $changes;
    }

    private static function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'sí' : 'no',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }
}
