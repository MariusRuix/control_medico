<?php

declare(strict_types=1);

namespace App\Services\Backups;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Genera el volcado de la base de datos: mysqldump para MariaDB/MySQL y
 * copia consistente (VACUUM INTO) para SQLite.
 */
final class DatabaseDumper
{
    /**
     * @return string nombre del archivo dentro del respaldo
     */
    public function dump(string $directory): string
    {
        $connection = self::connection();
        /** @var array<string, mixed> $config */
        $config = config("database.connections.{$connection}");
        $driver = (string) ($config['driver'] ?? '');

        return match ($driver) {
            'mysql', 'mariadb' => $this->mysql($config, $directory),
            'sqlite' => $this->sqlite($connection, $config, $directory),
            default => throw new RuntimeException("No se puede respaldar el motor de base de datos \"{$driver}\"."),
        };
    }

    /**
     * Conexión que se respalda (la predeterminada, salvo que config/backup.php indique otra).
     */
    public static function connection(): string
    {
        return (string) (config('backup.connection') ?: config('database.default'));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function mysql(array $config, string $directory): string
    {
        $file = $directory.DIRECTORY_SEPARATOR.'database.sql';

        $result = Process::timeout(900)
            // La contraseña va en una variable de entorno, no en la línea de comandos.
            ->env(['MYSQL_PWD' => (string) ($config['password'] ?? '')])
            ->run([
                (string) config('backup.mysqldump'),
                '--host='.($config['host'] ?? '127.0.0.1'),
                '--port='.($config['port'] ?? 3306),
                '--user='.($config['username'] ?? 'root'),
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--hex-blob',
                '--default-character-set=utf8mb4',
                '--result-file='.$file,
                (string) ($config['database'] ?? ''),
            ]);

        if ($result->failed()) {
            throw new RuntimeException('mysqldump falló: '.Str::limit(trim($result->errorOutput() ?: $result->output()), 300));
        }

        if (! is_file($file) || filesize($file) === 0) {
            throw new RuntimeException('mysqldump no generó el archivo del respaldo.');
        }

        return $file;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function sqlite(string $connection, array $config, string $directory): string
    {
        if (($config['database'] ?? '') === ':memory:') {
            throw new RuntimeException('No se puede respaldar una base de datos en memoria.');
        }

        $file = $directory.DIRECTORY_SEPARATOR.'database.sqlite';

        DB::connection($connection)->statement('VACUUM INTO ?', [$file]);

        if (! is_file($file)) {
            throw new RuntimeException('No se pudo copiar la base de datos SQLite.');
        }

        return $file;
    }
}
