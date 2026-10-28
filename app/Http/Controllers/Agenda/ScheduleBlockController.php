<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\ScheduleBlockRequest;
use App\Models\ScheduleBlock;
use App\Services\Agenda\ScheduleBlockService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ScheduleBlockController extends Controller
{
    public function __construct(private readonly ScheduleBlockService $blocks) {}

    public function store(ScheduleBlockRequest $request): RedirectResponse
    {
        ['affected' => $affected] = $this->blocks->create($request->blockData());

        Inertia::flash('toast', $affected > 0
            ? ['type' => 'warning', 'message' => "Horario bloqueado. Hay {$affected} cita(s) dentro del bloqueo que debes reprogramar."]
            : ['type' => 'success', 'message' => 'Horario bloqueado.']);

        return back();
    }

    public function destroy(ScheduleBlock $block): RedirectResponse
    {
        $this->blocks->delete($block);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bloqueo eliminado.']);

        return back();
    }
}
