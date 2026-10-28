<?php

declare(strict_types=1);

namespace App\Services\Prescriptions;

use App\Mail\PrescriptionMail;
use App\Models\Consultation;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FolioGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PrescriptionService
{
    public function __construct(
        private readonly FolioGenerator $folios,
        private readonly PrescriptionPdf $pdf,
        private readonly PrescriberProfile $prescriber,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Emite la receta: folio, partidas, huella de integridad y PDF.
     *
     * @param  array{general_instructions?: string|null, items: list<array<string, mixed>>}  $data
     */
    public function issue(Consultation $consultation, array $data, User $author): Prescription
    {
        $missing = $this->prescriber->missing();

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'prescriber' => 'Completa los datos del consultorio antes de emitir recetas: '.implode(', ', $missing).'.',
            ]);
        }

        try {
            $prescription = DB::transaction(function () use ($consultation, $data, $author): Prescription {
                $prescription = $consultation->prescriptions()->create([
                    'folio' => $this->folios->nextPrescriptionFolio(),
                    'general_instructions' => $data['general_instructions'] ?? null,
                    'issued_at' => now(),
                    'created_by' => $author->id,
                ]);

                foreach (array_values($data['items']) as $order => $item) {
                    $medicationId = $item['medication_id'] ?? null;

                    if ($medicationId === null && ($item['save_to_vademecum'] ?? false)) {
                        $medicationId = $this->saveToVademecum($item)->id;
                    }

                    $prescription->items()->create([
                        'medication_id' => $medicationId,
                        'medication_name' => $item['medication_name'],
                        'dose' => $item['dose'] ?? null,
                        'route' => $item['route'] ?? null,
                        'frequency' => $item['frequency'] ?? null,
                        'duration' => $item['duration'] ?? null,
                        'quantity' => $item['quantity'] ?? null,
                        'instructions' => $item['instructions'] ?? null,
                        'sort_order' => $order,
                    ]);
                }

                $prescription->forceFill(['signature_hash' => $this->fingerprint($prescription)])->saveQuietly();

                return $prescription;
            });
        } catch (Throwable $e) {
            Log::error('No se pudo emitir la receta.', ['consultation_id' => $consultation->id, 'error' => $e->getMessage()]);

            throw $e;
        }

        // El PDF se genera fuera de la transacción; si falla, se regenera al descargarlo.
        try {
            $this->pdf->store($prescription);
        } catch (Throwable) {
            // Ya registrado en PrescriptionPdf.
        }

        return $prescription;
    }

    /**
     * Anula la receta (soft delete) dejando el motivo en la bitácora.
     */
    public function void(Prescription $prescription, string $reason): void
    {
        try {
            DB::transaction(function () use ($prescription, $reason): void {
                $this->audit->log('voided', $prescription, [], ['reason' => $reason]);
                $prescription->delete();
            });
        } catch (Throwable $e) {
            Log::error('No se pudo anular la receta.', ['prescription_id' => $prescription->id, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Envía la receta en PDF al correo del paciente.
     */
    public function email(Prescription $prescription): string
    {
        $prescription->loadMissing('consultation');

        /** @var Patient $patient */
        $patient = Patient::withTrashed()->findOrFail($prescription->consultation?->patient_id);

        if (blank($patient->email)) {
            throw ValidationException::withMessages(['email' => 'El paciente no tiene correo electrónico registrado.']);
        }

        try {
            Mail::to((string) $patient->email, $patient->full_name)
                ->send(new PrescriptionMail($prescription, $patient, $this->pdf->contents($prescription), $this->pdf->filename($prescription)));

            $this->audit->log('emailed', $prescription, [], ['to' => $patient->email]);

            return (string) $patient->email;
        } catch (Throwable $e) {
            Log::error('No se pudo enviar la receta por correo.', ['prescription_id' => $prescription->id, 'error' => $e->getMessage()]);

            throw ValidationException::withMessages(['email' => 'No se pudo enviar el correo. Revisa la configuración de correo (MAIL_*) e intenta de nuevo.']);
        }
    }

    /**
     * Huella SHA-256 del contenido emitido: permite comprobar que la receta
     * impresa coincide con la registrada en el sistema.
     */
    public function fingerprint(Prescription $prescription): string
    {
        $prescription->loadMissing('items');

        $payload = [
            'folio' => $prescription->folio,
            'consultation_id' => $prescription->consultation_id,
            'issued_at' => $prescription->issued_at?->toIso8601String(),
            'general_instructions' => $prescription->general_instructions,
            'items' => $prescription->items->map(fn ($i): array => [
                $i->medication_name, $i->dose, $i->route, $i->frequency, $i->duration, $i->quantity, $i->instructions,
            ])->all(),
        ];

        return hash_hmac('sha256', (string) json_encode($payload), (string) config('app.key'));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function saveToVademecum(array $item): Medication
    {
        return Medication::query()->firstOrCreate(
            ['generic_name' => $item['medication_name']],
            [
                'route' => $item['route'] ?? null,
                'is_favorite' => true,
                'default_dose' => $item['dose'] ?? null,
                'default_frequency' => $item['frequency'] ?? null,
                'default_duration' => $item['duration'] ?? null,
                'default_instructions' => $item['instructions'] ?? null,
                'is_active' => true,
            ],
        );
    }
}
