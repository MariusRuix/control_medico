<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Enums\AppointmentStatus;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Recordatorios de citas por correo y/o WhatsApp.
 *
 * Configuración (settings): notifications.reminders_enabled,
 * notifications.reminder_hours_before y notifications.reminder_channel
 * ('email', 'whatsapp' o 'both').
 */
final class ReminderService
{
    public const array CHANNELS = ['email', 'whatsapp', 'both'];

    public const array HOURS_OPTIONS = [2, 4, 12, 24, 48];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly WhatsAppClient $whatsapp,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('notifications.reminders_enabled', false);
    }

    public function hoursBefore(): int
    {
        $hours = (int) $this->settings->get('notifications.reminder_hours_before', 24);

        return $hours >= 1 && $hours <= 72 ? $hours : 24;
    }

    public function channel(): string
    {
        $channel = (string) $this->settings->get('notifications.reminder_channel', 'email');

        return in_array($channel, self::CHANNELS, true) ? $channel : 'email';
    }

    /**
     * Citas activas que empiezan dentro de la ventana configurada y aún no tienen recordatorio.
     *
     * @return Collection<int, Appointment>
     */
    public function due(): Collection
    {
        return Appointment::query()
            ->with(['patient', 'service'])
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed])
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addHours($this->hoursBefore()))
            ->whereHas('patient')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Envía los recordatorios pendientes (lo ejecuta el programador de tareas).
     *
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function sendDue(): array
    {
        $summary = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        if (! $this->enabled()) {
            return $summary;
        }

        foreach ($this->due() as $appointment) {
            $result = $this->send($appointment);
            $summary[$result['status']]++;
        }

        return $summary;
    }

    /**
     * Envía el recordatorio de una cita por los canales configurados.
     *
     * @return array{status: 'sent'|'skipped'|'failed', channels: list<string>, errors: list<string>}
     */
    public function send(Appointment $appointment): array
    {
        $appointment->loadMissing(['patient', 'service']);
        $patient = $appointment->patient;
        $channel = $this->channel();
        $sent = [];
        $errors = [];

        if ($patient === null) {
            return ['status' => 'skipped', 'channels' => [], 'errors' => ['La cita no tiene paciente activo.']];
        }

        if (in_array($channel, ['email', 'both'], true)) {
            if (blank($patient->email)) {
                $errors[] = 'El paciente no tiene correo registrado.';
            } else {
                try {
                    Mail::to((string) $patient->email, $patient->full_name)->send(new AppointmentReminderMail($appointment));
                    $sent[] = 'correo';
                } catch (Throwable $e) {
                    Log::error('Falló el recordatorio por correo.', ['appointment_id' => $appointment->id, 'error' => $e->getMessage()]);
                    $errors[] = 'No se pudo enviar el correo (revisa la configuración MAIL_*).';
                }
            }
        }

        if (in_array($channel, ['whatsapp', 'both'], true)) {
            $phone = PhoneNumber::toInternational($patient->phone, (string) config('services.whatsapp.country_code', '52'));

            if (! $this->whatsapp->isConfigured()) {
                $errors[] = 'WhatsApp no está configurado.';
            } elseif ($phone === null) {
                $errors[] = 'El paciente no tiene un teléfono válido.';
            } else {
                try {
                    $this->whatsapp->sendTemplate($phone, [
                        $patient->first_name,
                        $appointment->starts_at->translatedFormat('l j \d\e F'),
                        $appointment->starts_at->format('H:i'),
                    ]);
                    $sent[] = 'WhatsApp';
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        if ($sent !== []) {
            $appointment->forceFill(['reminder_sent_at' => now()])->saveQuietly();

            return ['status' => 'sent', 'channels' => $sent, 'errors' => $errors];
        }

        // Sin forma de contactar al paciente: se marca como "omitido" para no reintentar cada 15 minutos.
        $unreachable = $errors !== [] && collect($errors)->every(fn (string $e): bool => str_contains($e, 'no tiene'));

        if ($unreachable) {
            $appointment->forceFill(['reminder_sent_at' => now()])->saveQuietly();
        }

        return ['status' => $unreachable ? 'skipped' : 'failed', 'channels' => [], 'errors' => $errors];
    }
}
