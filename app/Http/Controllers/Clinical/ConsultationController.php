<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\ConsultationRequest;
use App\Http\Requests\Clinical\SignConsultationRequest;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Clinical\ConsultationService;
use App\Services\Clinical\ConsultationWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsultationController extends Controller
{
    public function __construct(
        private readonly ConsultationService $consultations,
        private readonly ConsultationWorkspace $workspace,
        private readonly AuditLogger $audit,
    ) {}

    public function startFromAppointment(Request $request, Appointment $appointment): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $consultation = $this->consultations->startFromAppointment($appointment, $user);

        return to_route('consultations.edit', $consultation);
    }

    public function start(Request $request, Patient $patient): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $consultation = $this->consultations->startWalkIn($patient, $user);

        return to_route('consultations.edit', $consultation);
    }

    public function show(Consultation $consultation): Response|RedirectResponse
    {
        $this->audit->viewed($consultation);

        return Inertia::render('consultations/Show', $this->workspace->build($consultation, editable: false));
    }

    public function edit(Consultation $consultation): Response|RedirectResponse
    {
        if ($consultation->isSigned()) {
            return to_route('consultations.show', $consultation);
        }

        $this->audit->viewed($consultation);

        return Inertia::render('consultations/Edit', $this->workspace->build($consultation, editable: true));
    }

    public function update(ConsultationRequest $request, Consultation $consultation): RedirectResponse
    {
        $this->consultations->saveDraft($consultation, $request->noteData());

        return back();
    }

    public function sign(SignConsultationRequest $request, Consultation $consultation): RedirectResponse
    {
        $this->consultations->sign($consultation, $request->noteData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nota firmada.']);

        return to_route('consultations.show', $consultation);
    }

    public function destroy(Consultation $consultation): RedirectResponse
    {
        $patientId = $consultation->patient_id;

        $this->consultations->discard($consultation);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador descartado.']);

        return to_route('patients.show', $patientId);
    }
}
