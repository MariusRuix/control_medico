<?php

declare(strict_types=1);

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\PatientSearchRequest;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;

/**
 * Autocompletado de pacientes (agenda, cobros, etc.).
 */
class PatientSearchController extends Controller
{
    private const int LIMIT = 10;

    public function __invoke(PatientSearchRequest $request): JsonResponse
    {
        $patients = Patient::query()
            ->search((string) $request->validated('q'))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::LIMIT)
            ->get(['id', 'folio', 'first_name', 'last_name', 'second_last_name', 'phone', 'birth_date'])
            ->map(fn (Patient $p): array => [
                'id' => $p->id,
                'folio' => $p->folio,
                'full_name' => $p->full_name,
                'phone' => $p->phone,
                'age' => $p->age,
            ]);

        return response()->json(['data' => $patients]);
    }
}
