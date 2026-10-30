<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clinical\AddendumRequest;
use App\Models\Consultation;
use App\Models\User;
use App\Services\Clinical\AddendumService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ConsultationAddendumController extends Controller
{
    public function store(AddendumRequest $request, Consultation $consultation, AddendumService $addenda): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $addenda->add($consultation, (string) $request->validated('body'), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nota aclaratoria agregada.']);

        return back();
    }
}
