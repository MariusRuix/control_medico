<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Plantilla de nota médica: valores iniciales para los campos SOAP.
 *
 * @property int $id
 * @property string $name
 * @property array{reason?: string, subjective?: string, objective?: string, assessment?: string, plan?: string} $structure
 * @property bool $is_active
 */
#[Fillable(['name', 'structure', 'is_active'])]
class NoteTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'structure' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
