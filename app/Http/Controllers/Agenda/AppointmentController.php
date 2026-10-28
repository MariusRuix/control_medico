<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agenda;

use App\Enums\AppointmentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\AppointmentRequest;
use App\Http\Requests\Agenda\AppointmentStatusRequest;
use App\Http\Requests\Agenda\AvailabilityRequest;
use App\Http\Requests\Agenda\CalendarRequest;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Services\Agenda\AppointmentService;
use App\Services\Agenda\AvailabilityService;
use App\Services\Agenda\CalendarQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments) {}

    public function index(CalendarRequest $request, CalendarQuery $calendar): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('appointments/Index', [
            ...$calendar->build($request->calendarView(), $request->targetDate()),
            'services' => Service::query()
                ->appointable()
                ->orderBy('name')
                ->get(['id', 'name', 'duration_minutes', 'color']),
            'statuses' => AppointmentStatus::definitions(),
            'can' => [
                'manage' => $user->can(Permission::AppointmentsManage->value),
                'manageBlocks' => $user->can(Permission::SettingsManage->value),
                'viewPatients' => $user->can(Permission::PatientsView->value),
            ],
        ]);
    }

    public function availability(AvailabilityRequest $request, AvailabilityService $availability): JsonResponse
    {
        $ignore = $request->validated('ignore');

        return response()->json([
            'slots' => $availability->freeSlots(
                $request->targetDate(),
                (int) $request->validated('duration_minutes'),
                $ignore !== null ? (int) $ignore : null,
            ),
        ]);
    }

    public function store(AppointmentRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $appointment = $this->appointments->create($request->appointmentData(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cita agendada.']);

        return to_route('appointments.index', [
            'view' => $request->query('view', 'week'),
            'date' => $appointment->starts_at->toDateString(),
        ]);
    }

    public function update(AppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->appointments->update($appointment, $request->appointmentData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cita actualizada.']);

        return back();
    }

    public function status(AppointmentStatusRequest $request, Appointment $appointment): RedirectResponse
    {
        $status = $request->newStatus();

        $this->appointments->changeStatus($appointment, $status, $request->validated('cancel_reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Cita marcada como {$status->label()}."]);

        return back();
    }
}
