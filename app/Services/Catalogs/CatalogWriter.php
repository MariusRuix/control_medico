<?php

declare(strict_types=1);

namespace App\Services\Catalogs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Altas y cambios de los catálogos simples (medicamentos, servicios,
 * plantillas) con manejo de errores uniforme. Los modelos que usan el
 * trait Auditable quedan además en la bitácora.
 */
final class CatalogWriter
{
    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function save(Model $model, array $data): Model
    {
        try {
            $model->fill($data)->save();

            return $model;
        } catch (Throwable $e) {
            Log::error('No se pudo guardar el catálogo.', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function delete(Model $model): void
    {
        try {
            $model->delete();
        } catch (Throwable $e) {
            Log::error('No se pudo eliminar del catálogo.', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
