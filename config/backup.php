<?php

declare(strict_types=1);

return [

    /*
    | Carpeta donde se guardan los respaldos. Para que sirvan de algo deben
    | quedar FUERA de este equipo o sincronizarse: una carpeta de OneDrive /
    | Google Drive, un disco externo o una unidad de red (p. ej. "D:\Respaldos").
    */
    'path' => env('BACKUP_PATH') ?: storage_path('app/backups'),

    /*
    | Contraseña para cifrar el ZIP (AES-256). Guárdala en un lugar seguro
    | fuera del equipo: sin ella no se puede abrir el respaldo.
    */
    'password' => env('BACKUP_PASSWORD'),

    /*
    | Días que se conservan los respaldos. Siempre se conservan al menos
    | los últimos `keep_min`, aunque sean más antiguos.
    */
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),
    'keep_min' => 3,

    /*
    | Hora del respaldo automático diario (formato 24 h).
    */
    'time' => env('BACKUP_TIME') ?: '21:30',

    /*
    | Conexión de base de datos a respaldar (vacío = la predeterminada).
    */
    'connection' => env('BACKUP_DB_CONNECTION'),

    /*
    | Ruta de mysqldump. En Laragon, por ejemplo:
    | C:\laragon\bin\mysql\mariadb-11.4.2-winx64\bin\mysqldump.exe
    */
    'mysqldump' => env('BACKUP_MYSQLDUMP_PATH') ?: 'mysqldump',

    /*
    | Horas sin un respaldo exitoso antes de mostrar un aviso en Inicio.
    */
    'warn_after_hours' => 48,

];
