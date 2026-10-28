<?php

declare(strict_types=1);

namespace App\Enums;

enum Permission: string
{
    // Pacientes
    case PatientsView = 'patients.view';
    case PatientsManage = 'patients.manage';
    case PatientsDelete = 'patients.delete';
    case PatientHistoriesManage = 'patient-histories.manage';

    // Agenda
    case AppointmentsView = 'appointments.view';
    case AppointmentsManage = 'appointments.manage';

    // Expediente clínico
    case ConsultationsView = 'consultations.view';
    case ConsultationsManage = 'consultations.manage';
    case PrescriptionsManage = 'prescriptions.manage';
    case MedicalFilesView = 'medical-files.view';
    case MedicalFilesUpload = 'medical-files.upload';

    // Catálogos
    case CatalogsManage = 'catalogs.manage';

    // Finanzas
    case ChargesView = 'charges.view';
    case ChargesManage = 'charges.manage';
    case CashRegisterManage = 'cash-register.manage';
    case FinancialReportsView = 'financial-reports.view';

    // Sistema
    case SettingsManage = 'settings.manage';
    case UsersManage = 'users.manage';
    case AuditLogsView = 'audit-logs.view';
}
