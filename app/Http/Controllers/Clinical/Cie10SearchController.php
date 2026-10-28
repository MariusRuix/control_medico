<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\Cie10SearchRequest;
use App\Models\Cie10Code;
use Illuminate\Http\JsonResponse;

/**
 * Autocompletado de diagnósticos CIE-10 por código o descripción.
 */
class Cie10SearchController extends Controller
{
    private const int LIMIT = 15;

    public function __invoke(Cie10SearchRequest $request): JsonResponse
    {
        $term = (string) $request->validated('q');

        $codes = Cie10Code::query()
            ->search($term)
            // Código exacto, código que empieza igual, descripción que empieza igual y el resto.
            ->orderByRaw(
                'CASE WHEN code = ? THEN 0 WHEN code LIKE ? THEN 1 WHEN description LIKE ? THEN 2 ELSE 3 END',
                [strtoupper($term), strtoupper($term).'%', $term.'%'],
            )
            ->orderBy('code')
            ->limit(self::LIMIT)
            ->get(['id', 'code', 'description']);

        return response()->json(['data' => $codes]);
    }
}
