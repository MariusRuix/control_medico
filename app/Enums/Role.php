<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Medico = 'medico';
    case Recepcion = 'recepcion';

    public function label(): string
    {
        return match ($this) {
            self::Medico => 'Médico',
            self::Recepcion => 'Recepción',
        };
    }

    /**
     * Permisos asignados a cada rol.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Medico => Permission::cases(),
            self::Recepcion => [
                Permission::PatientsView,
                Permission::PatientsManage,
                Permission::AppointmentsView,
                Permission::AppointmentsManage,
                Permission::MedicalFilesUpload,
                Permission::ChargesView,
                Permission::ChargesManage,
                Permission::CashRegisterManage,
            ],
        };
    }
}
