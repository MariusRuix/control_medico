<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\MedicationSearchRequest;
use App\Http\Resources\MedicationOptionResource;
use App\Models\Medication;
use Illuminate\Http\JsonResponse;

/**
 * Autocompletado del catálogo de medicamentos (vademécum primero).
 */
class MedicationSearchController extends Controller
{
    private const int LIMIT = 15;

    public function __invoke(MedicationSearchRequest $request): JsonResponse
    {
        $medications = Medication::query()
            ->search((string) $request->validated('q'))
            ->orderByDesc('is_favorite')
            ->orderBy('generic_name')
            ->limit(self::LIMIT)
            ->get();

        return response()->json(['data' => MedicationOptionResource::collection($medications)->resolve()]);
    }
}
