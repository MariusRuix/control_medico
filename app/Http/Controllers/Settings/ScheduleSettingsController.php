<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ScheduleSettingsRequest;
use App\Services\Agenda\WorkingHours;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleSettingsController extends Controller
{
    public function edit(WorkingHours $hours): Response
    {
        return Inertia::render('settings/Schedule', [
            'workingHours' => $hours->weekly(),
            'slotMinutes' => $hours->slotMinutes(),
            'slotOptions' => ScheduleSettingsRequest::SLOT_OPTIONS,
        ]);
    }

    public function update(ScheduleSettingsRequest $request, SettingsService $settings): RedirectResponse
    {
        $settings->setMany([
            'schedule.working_hours' => $request->workingHours(),
            'schedule.slot_minutes' => (int) $request->validated('slot_minutes'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Horario de atención guardado.']);

        return to_route('settings.schedule.edit');
    }
}
