<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AuditLog;
use App\Services\Audit\AuditCatalog;
use App\Services\Audit\AuditLogQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Fila de la bitácora.
 *
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->toIso8601String(),
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null,
            'action' => $this->action,
            'action_label' => AuditCatalog::actionLabel($this->action),
            'type_label' => AuditCatalog::typeLabel($this->auditable_type),
            'auditable_id' => $this->auditable_id,
            'patient' => $this->patient ? [
                'id' => $this->patient->id,
                'folio' => $this->patient->folio,
                'full_name' => $this->patient->full_name,
            ] : null,
            'changes' => AuditLogQuery::changes($this->resource),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent !== null ? Str::limit($this->user_agent, 160) : null,
        ];
    }
}
