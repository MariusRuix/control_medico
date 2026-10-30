<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\Appointment;
use App\Models\CashRegister;
use App\Models\Charge;
use App\Models\Consultation;
use App\Models\ConsultationAddendum;
use App\Models\MedicalFile;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;

/**
 * Nombres legibles de las acciones y tipos de registro de la bitácora.
 */
final class AuditCatalog
{
    /** @var array<string, string> */
    public const array ACTIONS = [
        'viewed' => 'Consulta (lectura)',
        'downloaded' => 'Descarga',
        'created' => 'Alta',
        'updated' => 'Cambio',
        'deleted' => 'Baja',
        'force_deleted' => 'Borrado definitivo',
        'restored' => 'Restauración',
        'voided' => 'Anulación',
        'cancelled' => 'Cancelación',
        'emailed' => 'Envío por correo',
        'login' => 'Inicio de sesión',
        'logout' => 'Cierre de sesión',
        'login_failed' => 'Intento de acceso fallido',
        'activated' => 'Cuenta reactivada',
        'deactivated' => 'Cuenta desactivada',
        'password_reset' => 'Contraseña restablecida',
        'exported' => 'Exportación de bitácora',
    ];

    /**
     * Clave corta (para filtros y URL) => [clase del modelo, nombre].
     *
     * @var array<string, array{0: class-string, 1: string}>
     */
    public const array TYPES = [
        'patient' => [Patient::class, 'Paciente'],
        'patient_history' => [PatientHistory::class, 'Antecedente / alergia'],
        'appointment' => [Appointment::class, 'Cita'],
        'consultation' => [Consultation::class, 'Nota de consulta'],
        'addendum' => [ConsultationAddendum::class, 'Nota aclaratoria'],
        'prescription' => [Prescription::class, 'Receta'],
        'medical_file' => [MedicalFile::class, 'Estudio / archivo'],
        'charge' => [Charge::class, 'Cobro'],
        'payment' => [Payment::class, 'Pago'],
        'cash_register' => [CashRegister::class, 'Caja'],
        'user' => [User::class, 'Usuario'],
    ];

    public static function actionLabel(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }

    public static function typeLabel(?string $class): ?string
    {
        if ($class === null) {
            return null;
        }

        foreach (self::TYPES as [$model, $label]) {
            if ($model === $class) {
                return $label;
            }
        }

        return class_basename($class);
    }

    /**
     * @return class-string|null
     */
    public static function typeClass(?string $key): ?string
    {
        return $key !== null && isset(self::TYPES[$key]) ? self::TYPES[$key][0] : null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function actionOptions(): array
    {
        return array_map(
            fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_keys(self::ACTIONS),
            array_values(self::ACTIONS),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function typeOptions(): array
    {
        return array_map(
            fn (string $value, array $type): array => ['value' => $value, 'label' => $type[1]],
            array_keys(self::TYPES),
            array_values(self::TYPES),
        );
    }
}
