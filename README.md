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

| Rol         | Acceso                                                                                             |
| ----------- | -------------------------------------------------------------------------------------------------- |
| `medico`    | Todo: expediente, recetas, catálogos, finanzas, configuración, usuarios, bitácora                  |
| `recepcion` | Pacientes (ficha), agenda, subir estudios, cobros y caja. **No** ve notas clínicas ni diagnósticos |

Los permisos están en `app/Enums/Permission.php` y la asignación por rol en `app/Enums/Role.php`. Tras cambiarlos: `php artisan db:seed --class=RolesAndPermissionsSeeder`.

En rutas: `->middleware('permission:consultations.view')`. En Vue: `$page.props.auth.permissions`.

## Base de datos (19 tablas de negocio)

| Módulo     | Tablas                                                                                                                                      |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Sistema    | `settings`, `audit_logs` (+ `users` y tablas de Spatie)                                                                                     |
| Pacientes  | `patients`, `patient_histories` (antecedentes y alergias)                                                                                   |
| Agenda     | `services`, `schedule_blocks`, `appointments`                                                                                               |
| Expediente | `consultations` (SOAP + signos vitales), `consultation_diagnoses`, `prescriptions`, `prescription_items`, `medical_files`, `note_templates` |
| Catálogos  | `cie10_codes`, `medications` (incluye vademécum personal)                                                                                   |
| Finanzas   | `charges`, `charge_items`, `payments`, `cash_registers`                                                                                     |

## Decisiones de diseño

- **Configuración global** en `settings` con llaves `grupo.nombre` (`clinic.*`, `doctor.*`, `schedule.*`, `notifications.*`, `folios.*`). Úsala con `App\Services\SettingsService` (cacheada).
- **Cifrado en reposo** (cast `encrypted`): CURP, notas del paciente, antecedentes/alergias y toda la nota SOAP. La CURP se busca por `curp_hash` (HMAC).
- **Bitácora** automática con el trait `Auditable` (altas, cambios, bajas). Los valores cifrados nunca se copian a `audit_logs`. Para registrar consultas de lectura: `app(AuditLogger::class)->viewed($patient)`.
- **Nada se borra físicamente** del expediente (`softDeletes`); la NOM-004 exige conservarlo mínimo 5 años.
- **Folios** consecutivos con bloqueo de fila: `App\Services\FolioGenerator`.
- **Sin `doctor_id`**: hay un solo médico. Si entra otro, se agrega la columna con una migración.
- **Catálogo CIE-10**: `database/data/cie10.csv` trae solo diagnósticos frecuentes. Reemplázalo por el catálogo completo de la DGIS (columnas `code,description,chapter`) y ejecuta `php artisan db:seed --class=Cie10CodeSeeder`.

## Módulos terminados

### Pacientes (`/patients`)

- Directorio con búsqueda por nombre, teléfono, folio o CURP completa, y aviso de alergias críticas.
- Alta y edición de la ficha de identificación (folio automático, CURP validada y única).
- Expediente: ficha, alerta de alergias críticas (visible para todo el personal), antecedentes y alergias (solo médico, cifrados), historial de consultas, citas y saldo pendiente.
- Archivar paciente (solo médico): soft delete y cancelación de sus citas futuras.
- Cada consulta del expediente queda registrada en `audit_logs` (`viewed`).

Código: `app/Http/Controllers/Patients`, `app/Services/Patients`, `app/Http/Requests/Patients`, `resources/js/pages/patients`, `resources/js/components/patients`. Pruebas: `tests/Feature/Patients`.

### Agenda (`/appointments`)

- Calendario con vistas de día, semana y mes; color por estatus y resumen de citas del periodo.
- Las zonas fuera del horario de atención (`schedule.working_hours`) se muestran en gris; los bloqueos (`schedule_blocks`) con rayas.
- Nueva cita con clic en un espacio libre o con el botón: búsqueda de paciente, tipo de consulta (define la duración) y horarios libres calculados por el servidor.
- Validaciones en el servidor (dentro de una transacción con bloqueo): sin empalmes, sin bloqueos, dentro del horario de atención (salvo "sobrecupo") y no en el pasado.
- Flujo de estatus: Agendada → Confirmada → En consultorio → Finalizada, más Cancelada (con motivo) y No asistió. Las citas cerradas no se pueden reprogramar.
- Bloquear horario (vacaciones, festivos): solo el médico (`settings.manage`).

Código: `app/Http/Controllers/Agenda`, `app/Services/Agenda`, `app/Http/Requests/Agenda`, `resources/js/pages/appointments`, `resources/js/components/agenda`. Pruebas: `tests/Feature/Agenda`.
