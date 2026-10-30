<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ReminderSettingsRequest;
use App\Services\Reminders\ReminderService;
use App\Services\Reminders\WhatsAppClient;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReminderSettingsController extends Controller
{
    public function edit(ReminderService $reminders, WhatsAppClient $whatsapp): Response
    {
        return Inertia::render('settings/Reminders', [
            'enabled' => $reminders->enabled(),
            'hoursBefore' => $reminders->hoursBefore(),
            'channel' => $reminders->channel(),
            'hoursOptions' => ReminderService::HOURS_OPTIONS,
            'whatsappConfigured' => $whatsapp->isConfigured(),
            'mailer' => (string) config('mail.default'),
            'pending' => $reminders->enabled() ? $reminders->due()->count() : 0,
        ]);
    }

    public function update(ReminderSettingsRequest $request, SettingsService $settings, WhatsAppClient $whatsapp): RedirectResponse
    {
        $channel = (string) $request->validated('channel');

        if ($request->boolean('enabled') && $channel !== 'email' && ! $whatsapp->isConfigured()) {
            throw ValidationException::withMessages([
                'channel' => 'Para enviar por WhatsApp primero configura WHATSAPP_TOKEN y WHATSAPP_PHONE_NUMBER_ID en el archivo .env.',
            ]);
        }

        $settings->setMany([
            'notifications.reminders_enabled' => $request->boolean('enabled'),
            'notifications.reminder_hours_before' => (int) $request->validated('hours_before'),
            'notifications.reminder_channel' => $channel,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recordatorios guardados.']);

        return to_route('settings.reminders.edit');
    }
}
