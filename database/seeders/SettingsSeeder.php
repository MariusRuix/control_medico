<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Valores iniciales. Solo se crean si no existen, para no pisar lo que
     * el médico ya configuró.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            // Consultorio
            'clinic.name' => config('app.name'),
            'clinic.address' => null,
            'clinic.phone' => null,
            'clinic.email' => null,
            'clinic.logo_path' => null,

            // Médico (datos para la receta)
            'doctor.name' => config('medical.seed_users.medico.name'),
            'doctor.professional_license' => null,
            'doctor.specialty' => null,
            'doctor.specialty_license' => null,
            'doctor.university' => null,
            'doctor.signature_path' => null,
            'doctor.seal_path' => null,

            // Horario de atención: lista de rangos por día (mon..sun)
            'schedule.working_hours' => [
                'mon' => [['09:00', '14:00'], ['16:00', '20:00']],
                'tue' => [['09:00', '14:00'], ['16:00', '20:00']],
                'wed' => [['09:00', '14:00'], ['16:00', '20:00']],
                'thu' => [['09:00', '14:00'], ['16:00', '20:00']],
                'fri' => [['09:00', '14:00'], ['16:00', '20:00']],
                'sat' => [['09:00', '13:00']],
                'sun' => [],
            ],
            'schedule.slot_minutes' => 30,

            // Recordatorios
            'notifications.reminders_enabled' => false,
            'notifications.reminder_hours_before' => 24,
            'notifications.reminder_channel' => 'email',

            // Folios
            'folios.patient_prefix' => 'P-',
            'folios.patient_next' => 1,
            'folios.prescription_prefix' => 'R-',
            'folios.prescription_next' => 1,
        ];
    }

    public function run(SettingsService $settings): void
    {
        foreach (self::defaults() as $key => $value) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                ['group' => strstr($key, '.', true), 'value' => $value],
            );
        }

        $settings->flush();
    }
}
