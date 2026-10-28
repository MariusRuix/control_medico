<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Models\Appointment;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ScheduleBlockService
{
    /**
     * Crea el bloqueo y regresa cuántas citas activas quedan dentro de él
     * (no se cancelan solas: recepción debe reprogramarlas).
     *
     * @param  array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, reason?: string|null}  $data
     * @return array{block: ScheduleBlock, affected: int}
     */
    public function create(array $data): array
    {
        try {
            $block = ScheduleBlock::query()->create($data);

            $affected = Appointment::query()->overlapping($data['starts_at'], $data['ends_at'])->count();

            return ['block' => $block, 'affected' => $affected];
        } catch (Throwable $e) {
            Log::error('No se pudo crear el bloqueo de agenda.', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    public function delete(ScheduleBlock $block): void
    {
        try {
            $block->delete();
        } catch (Throwable $e) {
            Log::error('No se pudo eliminar el bloqueo de agenda.', ['block_id' => $block->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }
}
