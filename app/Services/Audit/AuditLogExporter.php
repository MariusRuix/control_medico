<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Exporta la bitácora filtrada a CSV (UTF-8 con BOM para abrirlo en Excel).
 * La exportación misma queda registrada en la bitácora.
 */
final class AuditLogExporter
{
    public function __construct(
        private readonly AuditLogQuery $query,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{patient?: int|null, user?: int|null, action?: string|null, type?: string|null, from?: string|null, to?: string|null}  $filters
     */
    public function download(array $filters): StreamedResponse
    {
        $builder = $this->query->filtered($filters)->with(['user:id,name', 'patient:id,folio,first_name,last_name,second_last_name']);

        $this->audit->log('exported', null, [], ['filters' => array_filter($filters), 'rows' => (clone $builder)->count()]);

        $filename = 'bitacora-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($builder): void {
            $this->write($builder);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  Builder<AuditLog>  $builder
     */
    private function write(Builder $builder): void
    {
        $out = fopen('php://output', 'w');

        if ($out === false) {
            throw new RuntimeException('No se pudo abrir la salida del CSV.');
        }

        try {
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha y hora', 'Usuario', 'Acción', 'Tipo de registro', 'ID registro', 'Folio paciente', 'Paciente', 'Cambios', 'IP'], escape: '');

            foreach ($builder->lazyByIdDesc(1000) as $log) {
                /** @var AuditLog $log */
                $changes = array_map(
                    fn (array $c): string => $c['field'].': '.($c['old'] ?? '∅').' → '.($c['new'] ?? '∅'),
                    AuditLogQuery::changes($log),
                );

                fputcsv($out, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user?->name ?? 'Sistema / sin sesión',
                    AuditCatalog::actionLabel($log->action),
                    AuditCatalog::typeLabel($log->auditable_type) ?? '',
                    $log->auditable_id ?? '',
                    $log->patient?->folio ?? '',
                    $log->patient?->full_name ?? '',
                    implode(' | ', $changes),
                    $log->ip_address ?? '',
                ], escape: '');
            }
        } catch (Throwable $e) {
            Log::error('Falló la exportación de la bitácora.', ['error' => $e->getMessage()]);

            throw $e;
        } finally {
            fclose($out);
        }
    }
}
