<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reminders\ReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('appointments:send-reminders')]
#[Description('Envía los recordatorios de las próximas citas (correo y/o WhatsApp)')]
class SendAppointmentReminders extends Command
{
    public function handle(ReminderService $reminders): int
    {
        if (! $reminders->enabled()) {
            $this->components->info('Los recordatorios están desactivados (Configuración → Recordatorios).');

            return self::SUCCESS;
        }

        $result = $reminders->sendDue();

        $this->components->info("Recordatorios: {$result['sent']} enviados, {$result['skipped']} sin datos de contacto, {$result['failed']} con error.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
