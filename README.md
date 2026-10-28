# Control Médico

Sistema de gestión para consultorio médico particular: agenda, expediente clínico electrónico (NOM-004-SSA3-2012), recetas, estudios y caja.

**Stack:** Laravel 13 · Vue 3 + Inertia · Tailwind CSS 4 · MariaDB · Fortify (2FA y passkeys) · spatie/laravel-permission

## Instalación (Laragon)

1. Crea la base de datos `control_medico` (utf8mb4_unicode_ci) en HeidiSQL o con:
   ```sql
   CREATE DATABASE control_medico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. En la terminal de Laragon, dentro de `C:\laragon\www\control_medico`:
   ```bash
   composer install
   copy .env.example .env
   php artisan key:generate
   ```
3. Edita `.env`:
   - `DB_USERNAME` / `DB_PASSWORD` de tu MariaDB.
   - `BLIND_INDEX_KEY`: genera una con `php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"`. **No la cambies después**: con ella se buscan las CURP.
   - `SEED_MEDICO_*` y `SEED_RECEPCION_*` con los correos (y contraseñas si quieres fijarlas).
4. Elige las funciones de acceso del starter kit:
   ```bash
   php artisan install:features
   ```
   Deja marcadas **Two-factor authentication**, **Passkeys** y **Password confirmation**, y **desmarca Registration y Email verification** (las cuentas las crea el seeder; no hay registro público). Este paso también ejecuta `npm install` y `npm run build`.
5. Migraciones y datos iniciales:
   ```bash
   php artisan migrate --seed
   ```
   Si dejaste las contraseñas vacías, el seeder imprime una contraseña temporal para cada usuario.
6. Desarrollo: `composer run dev` (o abre `http://control_medico.test` con Laragon y ejecuta `npm run dev`).

Pruebas: `php artisan test`.

## Roles

| Rol | Acceso |
|---|---|
| `medico` | Todo: expediente, recetas, catálogos, finanzas, configuración, usuarios, bitácora |
| `recepcion` | Pacientes (ficha), agenda, subir estudios, cobros y caja. **No** ve notas clínicas ni diagnósticos |

Los permisos están en `app/Enums/Permission.php` y la asignación por rol en `app/Enums/Role.php`. Tras cambiarlos: `php artisan db:seed --class=RolesAndPermissionsSeeder`.

En rutas: `->middleware('permission:consultations.view')`. En Vue: `$page.props.auth.permissions`.

## Base de datos (19 tablas de negocio)

| Módulo | Tablas |
|---|---|
| Sistema | `settings`, `audit_logs` (+ `users` y tablas de Spatie) |
| Pacientes | `patients`, `patient_histories` (antecedentes y alergias) |
| Agenda | `services`, `schedule_blocks`, `appointments` |
| Expediente | `consultations` (SOAP + signos vitales), `consultation_diagnoses`, `prescriptions`, `prescription_items`, `medical_files`, `note_templates` |
| Catálogos | `cie10_codes`, `medications` (incluye vademécum personal) |
| Finanzas | `charges`, `charge_items`, `payments`, `cash_registers` |

## Decisiones de diseño

- **Configuración global** en `settings` con llaves `grupo.nombre` (`clinic.*`, `doctor.*`, `schedule.*`, `notifications.*`, `folios.*`). Úsala con `App\Services\SettingsService` (cacheada).
- **Cifrado en reposo** (cast `encrypted`): CURP, notas del paciente, antecedentes/alergias y toda la nota SOAP. La CURP se busca por `curp_hash` (HMAC).
- **Bitácora** automática con el trait `Auditable` (altas, cambios, bajas). Los valores cifrados nunca se copian a `audit_logs`. Para registrar consultas de lectura: `app(AuditLogger::class)->viewed($patient)`.
- **Nada se borra físicamente** del expediente (`softDeletes`); la NOM-004 exige conservarlo mínimo 5 años.
- **Folios** consecutivos con bloqueo de fila: `App\Services\FolioGenerator`.
- **Sin `doctor_id`**: hay un solo médico. Si entra otro, se agrega la columna con una migración.
- **Catálogo CIE-10**: `database/data/cie10.csv` trae solo diagnósticos frecuentes. Reemplázalo por el catálogo completo de la DGIS (columnas `code,description,chapter`) y ejecuta `php artisan db:seed --class=Cie10CodeSeeder`.
