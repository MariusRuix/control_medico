<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Reminders\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AppointmentReminderController extends Controller
{
    /**
     * Envía el recordatorio en este momento (sin esperar al programador).
     */
    public function __invoke(Appointment $appointment, ReminderService $reminders): RedirectResponse
    {
        if ($appointment->status->isFinal()) {
            throw ValidationException::withMessages(['reminder' => 'La cita ya está cerrada.']);
        }

        $result = $reminders->send($appointment);

        if ($result['status'] !== 'sent') {
            throw ValidationException::withMessages(['reminder' => implode(' ', $result['errors']) ?: 'No se pudo enviar el recordatorio.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recordatorio enviado por '.implode(' y ', $result['channels']).'.']);

        return back();
    }
}
