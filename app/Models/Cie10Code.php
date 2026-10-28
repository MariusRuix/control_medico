<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $description
 * @property string|null $chapter
 * @property bool $is_active
 */
#[Fillable(['code', 'description', 'chapter', 'is_active'])]
class Cie10Code extends Model
{
    protected $table = 'cie10_codes';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Autocompletado por código o descripción.
     *
     * @param  Builder<Cie10Code>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = str_replace(['%', '_'], ['\%', '\_'], $term);

        $query->where('is_active', true)->where(function (Builder $q) use ($like): void {
            $q->where('code', 'like', $like.'%')
                ->orWhere('description', 'like', '%'.$like.'%');
        });
    }
}
