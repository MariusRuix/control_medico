<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Patient;
use App\Models\Prescription;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrescriptionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Prescription $prescription,
        public readonly Patient $patient,
        private readonly string $pdf,
        private readonly string $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu receta médica · Folio {$this->prescription->folio}",
        );
    }

    public function content(): Content
    {
        $settings = app(SettingsService::class);

        return new Content(
            markdown: 'mail.prescription',
            with: [
                'patientName' => $this->patient->first_name,
                'folio' => $this->prescription->folio,
                'clinicName' => $settings->get('clinic.name', config('app.name')),
                'doctorName' => $settings->get('doctor.name'),
                'clinicPhone' => $settings->get('clinic.phone'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, $this->filename)->withMime('application/pdf'),
        ];
    }
}
