<?php

declare(strict_types=1);

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\AuditLogIndexRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\Patient;
use App\Models\User;
use App\Services\Audit\AuditCatalog;
use App\Services\Audit\AuditLogExporter;
use App\Services\Audit\AuditLogQuery;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    private const int PER_PAGE = 50;

    public function index(AuditLogIndexRequest $request, AuditLogQuery $query): Response
    {
        $filters = $request->filters();

        $logs = $query->filtered($filters)
            ->with(['user:id,name', 'patient:id,folio,first_name,last_name,second_last_name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $patient = $filters['patient'] !== null
            ? Patient::withTrashed()->find($filters['patient'], ['id', 'folio', 'first_name', 'last_name', 'second_last_name', 'phone', 'birth_date'])
            : null;

        return Inertia::render('audit/Index', [
            'logs' => AuditLogResource::collection($logs),
            'filters' => $filters,
            'patient' => $patient ? [
                'id' => $patient->id,
                'folio' => $patient->folio,
                'full_name' => $patient->full_name,
                'phone' => $patient->phone,
                'age' => $patient->age,
            ] : null,
            'users' => User::query()->orderBy('name')->get(['id', 'name'])->map(fn (User $u): array => ['value' => $u->id, 'label' => $u->name]),
            'actions' => AuditCatalog::actionOptions(),
            'types' => AuditCatalog::typeOptions(),
        ]);
    }

    public function export(AuditLogIndexRequest $request, AuditLogExporter $exporter): StreamedResponse
    {
        return $exporter->download($request->filters());
    }
}
