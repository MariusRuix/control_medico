<?php

declare(strict_types=1);

namespace App\Services\Prescriptions;

use App\Models\Patient;
use App\Models\Prescription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Genera y guarda el PDF de la receta en el disco privado de archivos médicos.
 */
final class PrescriptionPdf
{
    public function __construct(private readonly PrescriberProfile $prescriber) {}

    public function render(Prescription $prescription): string
    {
        $prescription->loadMissing(['items', 'consultation']);
        $consultation = $prescription->consultation;

        if ($consultation === null) {
            throw new RuntimeException("La receta {$prescription->folio} no tiene consulta asociada.");
        }

        /** @var Patient $patient */
        $patient = Patient::withTrashed()->findOrFail($consultation->patient_id);

        $html = view('pdf.prescription', [
            'prescription' => $prescription,
            'consultation' => $consultation,
            'patient' => $patient,
            'allergies' => $patient->criticalAllergies()->get()->map(fn ($h): string => $h->description)->all(),
            'profile' => $this->prescriber->toArray(),
            'signature' => $this->prescriber->imageDataUri('doctor.signature_path'),
            'seal' => $this->prescriber->imageDataUri('doctor.seal_path'),
            'logo' => $this->prescriber->imageDataUri('clinic.logo_path'),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('letter')->output();
    }

    /**
     * Genera el PDF y guarda su ruta en la receta.
     */
    public function store(Prescription $prescription): string
    {
        try {
            $path = sprintf('prescriptions/%s/%s.pdf', $prescription->issued_at?->format('Y') ?? now()->format('Y'), $prescription->folio);

            Storage::disk($this->disk())->put($path, $this->render($prescription));

            $prescription->forceFill(['pdf_path' => $path])->saveQuietly();

            return $path;
        } catch (Throwable $e) {
            Log::error('No se pudo generar el PDF de la receta.', ['prescription_id' => $prescription->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Contenido del PDF; si no existe (o se borró), se vuelve a generar.
     */
    public function contents(Prescription $prescription): string
    {
        $disk = Storage::disk($this->disk());

        if ($prescription->pdf_path === null || ! $disk->exists($prescription->pdf_path)) {
            $this->store($prescription);
        }

        return (string) $disk->get((string) $prescription->pdf_path);
    }

    public function filename(Prescription $prescription): string
    {
        return "receta-{$prescription->folio}.pdf";
    }

    private function disk(): string
    {
        return (string) config('medical.files_disk');
    }
}
