<?php

declare(strict_types=1);

return [

    /*
    | Llave para los "blind index" (HMAC) que permiten buscar por coincidencia
    | exacta en columnas cifradas, como la CURP. Si no se define, se usa
    | APP_KEY. Si la cambias, hay que regenerar los hashes existentes.
    */
    'blind_index_key' => env('BLIND_INDEX_KEY', env('APP_KEY')),

    /*
    | Disco donde se guardan estudios y archivos adjuntos. Debe ser privado
    | (nunca "public"): los archivos se sirven a través de un controlador
    | que valida permisos.
    */
    'files_disk' => env('MEDICAL_FILES_DISK', 'local'),

    /*
    | Tamaño máximo de archivo en kilobytes (20 MB por defecto).
    */
    'max_upload_kb' => (int) env('MEDICAL_MAX_UPLOAD_KB', 20480),

    /*
    | Usuarios iniciales que crea el seeder (UserSeeder).
    */
    'seed_users' => [
        'medico' => [
            'name' => env('SEED_MEDICO_NAME', 'Médico'),
            'email' => env('SEED_MEDICO_EMAIL'),
            'password' => env('SEED_MEDICO_PASSWORD'),
        ],
        'recepcion' => [
            'name' => env('SEED_RECEPCION_NAME', 'Recepción'),
            'email' => env('SEED_RECEPCION_EMAIL'),
            'password' => env('SEED_RECEPCION_PASSWORD'),
        ],
    ],

];
