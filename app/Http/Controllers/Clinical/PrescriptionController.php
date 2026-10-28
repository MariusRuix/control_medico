<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\IssuePrescriptionRequest;
use App\Http\Requests\Clinical\VoidPrescriptionRequest;
use App\Models\Consultation;
use App\Models\Prescription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Prescriptions\PrescriptionPdf;
use App\Services\Prescriptions\PrescriptionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PrescriptionController extends Controller
{
    public function __construct(private readonly PrescriptionService $prescriptions) {}

    public function store(IssuePrescriptionRequest $request, Consultation $consultation): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $prescription = $this->prescriptions->issue($consultation, $request->prescriptionData(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Receta {$prescription->folio} emitida."]);

        return back();
    }

    public function pdf(Prescription $prescription, PrescriptionPdf $pdf, AuditLogger $audit): Response
    {
        $audit->viewed($prescription);

        return response($pdf->contents($prescription), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename($prescription).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function email(Prescription $prescription): RedirectResponse
    {
        $to = $this->prescriptions->email($prescription);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Receta enviada a {$to}."]);

        return back();
    }

    public function destroy(VoidPrescriptionRequest $request, Prescription $prescription): RedirectResponse
    {
        $this->prescriptions->void($prescription, (string) $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Receta {$prescription->folio} anulada."]);

        return back();
    }
}
