<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Appointment;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio de tu cita · '.$this->appointment->starts_at->translatedFormat('l j \d\e F, H:i'),
        );
    }

    public function content(): Content
    {
        $settings = app(SettingsService::class);
        $this->appointment->loadMissing(['patient', 'service']);

        return new Content(
            markdown: 'mail.appointment-reminder',
            with: [
                'patientName' => $this->appointment->patient?->first_name,
                'date' => $this->appointment->starts_at->translatedFormat('l j \d\e F \d\e Y'),
                'time' => $this->appointment->starts_at->format('H:i'),
                'service' => $this->appointment->service?->name,
                'clinicName' => $settings->get('clinic.name', config('app.name')),
                'clinicAddress' => $settings->get('clinic.address'),
                'clinicPhone' => $settings->get('clinic.phone'),
                'doctorName' => $settings->get('doctor.name'),
            ],
        );
    }
}
