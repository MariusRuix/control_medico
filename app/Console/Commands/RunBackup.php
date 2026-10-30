<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Backups\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Number;
use Throwable;

#[Signature('backup:run')]
#[Description('Respalda la base de datos y los archivos privados del consultorio en un ZIP cifrado')]
class RunBackup extends Command
{
    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->run();
        } catch (Throwable $e) {
            $this->components->error('El respaldo falló: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Respaldo creado: %s (%s, %d archivo(s)). Respaldos antiguos eliminados: %d.',
            $result['file'],
            Number::fileSize($result['size'], precision: 1),
            $result['files'],
            $result['pruned'],
        ));

        if (! $backups->encrypted()) {
            $this->components->warn('El respaldo NO está cifrado: define BACKUP_PASSWORD en .env.');
        }

        return self::SUCCESS;
    }
}
