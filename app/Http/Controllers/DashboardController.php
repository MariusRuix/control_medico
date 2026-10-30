<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\Permission;
use App\Models\User;
use App\Services\Backups\BackupService;
use App\Services\Billing\BillingOptions;
use App\Services\Dashboard\DashboardQuery;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardQuery $dashboard, BillingOptions $billing, SettingsService $settings, BackupService $backups): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Dashboard', [
            ...$dashboard->forUser($user),
            'statuses' => AppointmentStatus::definitions(),
            'billing' => $user->can(Permission::ChargesManage->value) ? $billing->toArray() : null,
            'backupWarning' => $user->can(Permission::SettingsManage->value) ? $backups->warning() : null,
            'needsSetup' => $user->can(Permission::SettingsManage->value) && blank($settings->get('doctor.professional_license')),
            'can' => [
                'manageAppointments' => $user->can(Permission::AppointmentsManage->value),
                'viewPatients' => $user->can(Permission::PatientsView->value),
                'consult' => $user->can(Permission::ConsultationsManage->value),
            ],
        ]);
    }
}
