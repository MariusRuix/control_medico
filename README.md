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

## Base de datos (20 tablas de negocio)

| Módulo     | Tablas                                                                                                                                                              |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Sistema    | `settings`, `audit_logs` (+ `users` y tablas de Spatie)                                                                                                             |
| Pacientes  | `patients`, `patient_histories` (antecedentes y alergias)                                                                                                           |
| Agenda     | `services`, `schedule_blocks`, `appointments`                                                                                                                       |
| Expediente | `consultations` (SOAP + signos vitales), `consultation_diagnoses`, `consultation_addenda`, `prescriptions`, `prescription_items`, `medical_files`, `note_templates` |
| Catálogos  | `cie10_codes`, `medications` (incluye vademécum personal)                                                                                                           |
| Finanzas   | `charges`, `charge_items`, `payments`, `cash_registers`                                                                                                             |

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

### Consulta (`/consultations`)

- Se inicia desde la cita ("Iniciar consulta" en la agenda; la cita pasa a _En consultorio_) o desde el expediente para pacientes sin cita.
- Pantalla de consulta: signos vitales con IMC y clasificación en vivo, nota SOAP, plantillas (llenan solo los campos vacíos) y diagnósticos CIE-10 con autocompletado (uno principal).
- Panel lateral con alergias críticas, antecedentes, evolución de signos vitales y notas anteriores.
- Guardado automático (y Ctrl+S); validación de rangos fisiológicos para detectar errores de captura.
- Firmar: exige evolución (S), plan, diagnóstico principal y que no quede texto de plantilla sin llenar. La nota firmada queda bloqueada y la cita se marca como _Finalizada_.
- Descartar borrador: la cita regresa a _Confirmada_. Vista de nota firmada lista para imprimir.
- Solo el médico (`consultations.view` / `consultations.manage`); cada apertura queda en la bitácora.

Código: `app/Http/Controllers/Clinical`, `app/Services/Clinical`, `app/Http/Requests/Clinical`, `resources/js/pages/consultations`, `resources/js/components/clinical`. Pruebas: `tests/Feature/Clinical`.

### Recetas

- Se emiten desde la consulta (borrador o firmada): vademécum con un clic, buscador del catálogo o medicamento en texto libre (opción de guardarlo en el vademécum con su posología).
- Aviso de **posible alergia** cuando el medicamento comparte raíz con una alergia crítica del paciente (p. ej. _Sulfametoxazol_ y _Sulfas_).
- Al emitir: folio consecutivo (`folios.prescription_*`), huella HMAC de integridad (código de verificación impreso) y PDF tamaño carta guardado en el disco privado. Las recetas emitidas no se editan; se anulan con motivo (queda en la bitácora).
- El PDF incluye los datos que exige la receta: médico, cédula profesional, institución, domicilio, fecha, paciente, alergias, medicamentos con dosis/vía/frecuencia/duración y firma.
- Envío por correo al paciente con el PDF adjunto (configura `MAIL_*` en `.env`; con `MAIL_MAILER=log` el correo solo se escribe en `storage/logs`).
- **Configuración → Consultorio** (`/settings/clinic`, solo médico): datos del médico y del consultorio, firma, sello y logotipo. No se pueden emitir recetas hasta completar nombre, cédula, institución y domicilio.
- Dependencia: `barryvdh/laravel-dompdf`.

Código: `app/Services/Prescriptions`, `app/Http/Controllers/Clinical/PrescriptionController.php`, `app/Http/Controllers/Settings/ClinicSettingsController.php`, `resources/views/pdf/prescription.blade.php`, `resources/js/components/prescriptions`, `resources/js/pages/settings/Clinic.vue`. Pruebas: `tests/Feature/Clinical/PrescriptionTest.php`.

### Estudios y archivos

- Panel "Estudios y archivos" en el expediente del paciente y en la consulta (con opción de vincular el estudio a la consulta).
- Subida múltiple (hasta 10 archivos, 20 MB c/u por defecto, `MEDICAL_MAX_UPLOAD_KB`) con arrastrar y soltar y barra de progreso. Solo PDF, JPG, PNG y WEBP; se valida extensión y tipo real del contenido.
- Se guardan en el disco privado (`MEDICAL_FILES_DISK`) con nombre aleatorio en `patients/{id}/`; nunca son accesibles por URL pública.
- Visor integrado (imágenes y PDF) con navegación entre estudios, descarga y "abrir en otra pestaña". Cada vista o descarga queda en la bitácora.
- Permisos: recepción **sube** y ve el listado (título, tipo, fecha) pero no abre los archivos; el médico ve, descarga y elimina (con motivo). Al eliminar, el archivo físico se conserva (NOM-004).
- Nuevo permiso `medical-files.delete`: ejecuta `php artisan db:seed --class=RolesAndPermissionsSeeder`.

Código: `app/Services/MedicalFiles`, `app/Http/Controllers/MedicalFiles`, `app/Http/Requests/MedicalFiles`, `resources/js/components/files`. Pruebas: `tests/Feature/MedicalFiles`.

### Caja y cobros (`/cash`)

- **Apertura de caja** con fondo inicial; solo puede haber una caja abierta. Los pagos siempre se registran en la caja abierta.
- **Cobros** con uno o varios conceptos (servicios del catálogo o conceptos libres), cantidad, precio y descuento; el total se recalcula en el servidor (en centavos, sin errores de redondeo).
- Cobrar en el momento (efectivo con cálculo de cambio, transferencia o tarjeta con referencia) o dejarlo como **adeudo**. Abonos parciales hasta liquidar.
- Accesos directos: "Nuevo cobro" en Caja, en el expediente del paciente y "Registrar cobro" desde la cita en la agenda (precarga el servicio).
- **Corte de caja**: totales por método, efectivo esperado (fondo + efectivo cobrado) contra contado, faltante o sobrante y notas. Reporte imprimible e historial de cortes.
- Correcciones con motivo (bitácora): cancelar un cobro sin pagos y cancelar un pago mientras su caja siga abierta (los cortes cerrados no se alteran).
- Permisos: recepción y médico (`charges.view`, `charges.manage`, `cash-register.manage`).

Código: `app/Services/Billing`, `app/Http/Controllers/Billing`, `app/Http/Requests/Billing`, `app/Support/Money.php`, `resources/js/pages/cash`, `resources/js/components/billing`. Pruebas: `tests/Feature/Billing`.

### Inicio (`/dashboard`)

- La raíz del sitio lleva directo a Inicio (o al inicio de sesión).
- Indicadores del día: citas (y de mañana), por atender, en consultorio, atendidas, no asistieron; para quien maneja caja, lo cobrado hoy, estado de la caja y total de adeudos.
- Citas de hoy con acciones rápidas según el rol: confirmar, pasar a consultorio (recepción), iniciar o continuar la consulta (médico) y cobrar las citas finalizadas. La próxima cita por atender se resalta.
- Notas sin firmar (solo médico) para retomarlas con un clic, y aviso si faltan los datos del consultorio para emitir recetas.

Código: `app/Http/Controllers/DashboardController.php`, `app/Services/Dashboard/DashboardQuery.php`, `resources/js/pages/Dashboard.vue`. Pruebas: `tests/Feature/HomeDashboardTest.php`.

### Catálogos (`/catalogs`) y horario (`/settings/schedule`)

- **Vademécum**: alta y edición de medicamentos con posología predeterminada, marcar como favorito con un clic (aparece en las recetas), desactivar. Evita duplicados del mismo genérico, concentración y presentación.
- **Servicios y precios**: precio, si se puede agendar (con duración y color) y activo/inactivo. Se usan en la agenda y en los cobros.
- **Plantillas de nota**: texto inicial de cada apartado SOAP; se aplican desde la consulta.
- **Diagnósticos CIE-10**: importación del catálogo completo de la DGIS desde CSV (reconoce `CATALOG_KEY, NOMBRE` o `code, description`, separador coma/punto y coma/tabulador y archivos en UTF-8 o Latin-1), búsqueda y ocultar códigos del autocompletado.
- **Horario de atención** (Configuración → Horario): rangos por día sin empalmes, copiar el lunes al resto de la semana e intervalo de la agenda.
- Solo el médico (`catalogs.manage`, `settings.manage`).

Código: `app/Http/Controllers/Catalogs`, `app/Services/Catalogs`, `app/Http/Requests/Catalogs`, `app/Http/Controllers/Settings/ScheduleSettingsController.php`, `resources/js/pages/catalogs`, `resources/js/pages/settings/Schedule.vue`. Pruebas: `tests/Feature/Catalogs`.

### Notas aclaratorias (addenda)

- En una nota firmada, el médico puede agregar notas aclaratorias para corregir o complementar sin modificar el registro original (NOM-004-SSA3-2012).
- Cada nota aclaratoria queda con autor, fecha y hora, cifrada en reposo y en la bitácora; el modelo impide editarlas o borrarlas.
- Se muestran (y se imprimen) debajo de los diagnósticos de la nota. Los borradores se siguen editando directamente.

Código: `app/Models/ConsultationAddendum.php`, `app/Services/Clinical/AddendumService.php`, `resources/js/components/clinical/AddendaSection.vue`. Migración: `2026_10_30_000001_create_consultation_addenda_table`. Pruebas: `tests/Feature/Clinical/AddendumTest.php`.

### Recordatorios de citas

- Se activan en **Configuración → Recordatorios**: horas de anticipación (2, 4, 12, 24 o 48) y canal (correo, WhatsApp o ambos).
- Se envían una sola vez por cita (`reminder_sent_at`) a citas agendadas o confirmadas. Si el paciente no tiene correo ni teléfono, la cita se marca para no reintentar.
- En el detalle de la cita (agenda) hay un botón **Enviar/Reenviar recordatorio** y un enlace de WhatsApp manual (`wa.me`), que funciona sin la API.
- Correo: configurar `MAIL_*` en `.env`. WhatsApp (opcional): `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID` y una plantilla aprobada en Meta (`WHATSAPP_REMINDER_TEMPLATE`, por defecto `recordatorio_cita`, con parámetros: nombre, fecha, hora y consultorio).
- Requiere el programador de tareas de Laravel (cada 15 minutos). En Laragon, deja corriendo `php artisan schedule:work`, o crea una tarea en el Programador de tareas de Windows que ejecute `php artisan schedule:run` cada minuto. Envío manual: `php artisan appointments:send-reminders`.

Código: `app/Services/Reminders`, `app/Mail/AppointmentReminderMail.php`, `app/Console/Commands/SendAppointmentReminders.php`, `app/Http/Controllers/Agenda/AppointmentReminderController.php`, `app/Http/Controllers/Settings/ReminderSettingsController.php`, `resources/js/pages/settings/Reminders.vue`. Pruebas: `tests/Feature/Agenda/ReminderTest.php`.

### Usuarios (`/settings/users`)

- **Configuración → Usuarios** (solo el médico, `users.manage`; pide confirmar la contraseña): alta con rol Médico o Recepción, edición de nombre, correo y rol, restablecer contraseña y desactivar o reactivar.
- Si no se indica contraseña, se genera una temporal fácil de dictar (p. ej. `gsMv-7r4j-Kwkz`) que se muestra **una sola vez**; no se guarda en la bitácora ni en el historial del navegador.
- Al restablecer se puede quitar la verificación en dos pasos y las llaves de acceso (teléfono perdido).
- Las cuentas **no se borran** (se quitó "Eliminar cuenta" del perfil): se desactivan. Al desactivar se cierran sus sesiones y el middleware `EnsureUserIsActive` expulsa cualquier sesión que siga abierta.
- Reglas: nadie cambia su propio rol ni se desactiva a sí mismo, y siempre queda al menos un médico activo.
- Todo queda en la bitácora (`created`, `updated`, `activated`, `deactivated`, `password_reset`).

Código: `app/Services/Users`, `app/Http/Controllers/Users/UserController.php`, `app/Http/Requests/Users`, `app/Http/Middleware/EnsureUserIsActive.php`, `routes/users.php`, `resources/js/pages/settings/Users.vue`. Pruebas: `tests/Feature/Users`.

### Bitácora (`/audit-logs`)

- Menú **Bitácora** (solo el médico, `audit-logs.view`) y botón **Bitácora** en el expediente de cada paciente.
- Filtros por paciente, usuario, acción, tipo de registro y periodo (atajos: hoy, 7 y 30 días). Cada fila se expande para ver los campos que cambiaron (antes / después) y el equipo desde el que se hizo.
- Registra altas, cambios, bajas, lecturas del expediente, descargas, anulaciones, envíos por correo, cambios de cuentas, y además **inicios y cierres de sesión e intentos fallidos**.
- Cada registro guarda el paciente al que pertenece (`audit_logs.patient_id`); la migración completa los registros anteriores.
- **Exportar CSV** con los filtros actuales (se abre en Excel); la exportación también queda en la bitácora.
- Los registros no se pueden modificar ni borrar desde la aplicación, y los valores cifrados aparecen como `[protegido]`.

Código: `app/Services/Audit`, `app/Services/AuditLogger.php`, `app/Listeners/LogAuthenticationEvents.php`, `app/Http/Controllers/Audit/AuditLogController.php`, `resources/js/pages/audit/Index.vue`. Migración: `2026_10_31_000001_add_patient_id_to_audit_logs_table`. Pruebas: `tests/Feature/Audit`.

### Reportes financieros (`/reports/finance`)

- Menú **Reportes** (solo el médico, `financial-reports.view`). Periodo libre (máximo dos años) con atajos: este mes, mes anterior, últimos 30 días y este año.
- **Indicadores:** ingresos cobrados (con comparación contra el periodo anterior de la misma duración), facturado y cobro promedio, descuentos otorgados y adeudos a hoy.
- **Ingresos por día, semana o mes** (se elige sola según el periodo; se puede cambiar), **por método de pago** y **por servicio** (los conceptos libres aparecen como "Otros conceptos").
- **Antigüedad de adeudos** a hoy (0–30, 31–60, 61–90 y más de 90 días) y los 10 pacientes con mayor saldo.
- Definiciones: _ingresos_ = pagos recibidos en el periodo; _facturado_ = cobros no cancelados creados en el periodo, pagados o no.
- **Exportar a Excel (CSV):** detalle de pagos del periodo, ingresos por servicio y adeudos a hoy. Cada exportación queda en la bitácora.

Código: `app/Services/Reports`, `app/Http/Controllers/Reports/FinancialReportController.php`, `app/Http/Requests/Reports`, `routes/reports.php`, `resources/js/pages/reports/Finance.vue`. Pruebas: `tests/Feature/Reports`.
