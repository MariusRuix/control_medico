<?php

declare(strict_types=1);

namespace App\Services\Clinical;

use App\Models\Consultation;
use App\Models\ConsultationAddendum;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Notas aclaratorias: la única forma de corregir o complementar una nota
 * firmada sin alterar el registro original.
 */
final class AddendumService
{
    public function add(Consultation $consultation, string $body, User $author): ConsultationAddendum
    {
        if (! $consultation->isSigned()) {
            throw ValidationException::withMessages([
                'body' => 'La nota aún no está firmada: edítala directamente.',
            ]);
        }

        try {
            return $consultation->addenda()->create([
                'body' => $body,
                'created_by' => $author->id,
            ]);
        } catch (Throwable $e) {
            Log::error('No se pudo agregar la nota aclaratoria.', ['consultation_id' => $consultation->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }
}
