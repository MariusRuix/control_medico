<?php

declare(strict_types=1);

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\PatientHistoryRequest;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\User;
use App\Services\Patients\PatientHistoryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PatientHistoryController extends Controller
{
    public function __construct(private readonly PatientHistoryService $histories) {}

    public function store(PatientHistoryRequest $request, Patient $patient): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->histories->create($patient, $request->validated(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Antecedente agregado.']);

        return to_route('patients.show', $patient);
    }

    public function update(PatientHistoryRequest $request, Patient $patient, PatientHistory $history): RedirectResponse
    {
        $this->histories->update($history, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Antecedente actualizado.']);

        return to_route('patients.show', $patient);
    }

    public function destroy(Patient $patient, PatientHistory $history): RedirectResponse
    {
        $this->histories->delete($history);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Antecedente eliminado.']);

        return to_route('patients.show', $patient);
    }
}
