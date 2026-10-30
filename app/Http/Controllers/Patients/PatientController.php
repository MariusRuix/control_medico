<?php

declare(strict_types=1);

namespace App\Http\Controllers\Patients;

use App\Enums\BloodType;
use App\Enums\MedicalFileType;
use App\Enums\PatientHistoryType;
use App\Enums\Permission;
use App\Enums\Sex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\PatientIndexRequest;
use App\Http\Requests\Patients\PatientRequest;
use App\Http\Resources\PatientListResource;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Billing\BillingOptions;
use App\Services\Patients\PatientProfileQuery;
use App\Services\Patients\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    private const int PER_PAGE = 15;

    public function __construct(
        private readonly PatientService $patients,
        private readonly AuditLogger $audit,
    ) {}

    public function index(PatientIndexRequest $request): Response
    {
        $search = $request->validated('q');

        $patients = Patient::query()
            ->search($search)
            ->withExists('criticalAllergies')
            ->orderBy('last_name')
            ->orderBy('second_last_name')
            ->orderBy('first_name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('patients/Index', [
            'patients' => PatientListResource::collection($patients),
            'filters' => ['q' => $search],
            'can' => [
                'create' => $request->user()?->can(Permission::PatientsManage->value) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('patients/Create', $this->formOptions());
    }

    public function store(PatientRequest $request): RedirectResponse
    {
        $patient = $this->patients->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Paciente registrado con folio {$patient->folio}."]);

        return to_route('patients.show', $patient);
    }

    public function show(Request $request, Patient $patient, PatientProfileQuery $profile, BillingOptions $billing): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->audit->viewed($patient);

        return Inertia::render('patients/Show', [
            'patient' => PatientResource::make($patient)->resolve(),
            ...$profile->forUser($patient, $user),
            'historyTypes' => PatientHistoryType::options(),
            'fileTypes' => MedicalFileType::options(),
            'billing' => $user->can(Permission::ChargesManage->value) ? $billing->toArray() : null,
            'can' => [
                'update' => $user->can(Permission::PatientsManage->value),
                'delete' => $user->can(Permission::PatientsDelete->value),
                'manageHistories' => $user->can(Permission::PatientHistoriesManage->value),
                'consult' => $user->can(Permission::ConsultationsManage->value) && ! $patient->trashed(),
                'uploadFiles' => $user->can(Permission::MedicalFilesUpload->value),
                'viewFiles' => $user->can(Permission::MedicalFilesView->value),
                'deleteFiles' => $user->can(Permission::MedicalFilesDelete->value),
            ],
        ]);
    }

    public function edit(Patient $patient): Response
    {
        return Inertia::render('patients/Edit', [
            'patient' => PatientResource::make($patient)->resolve(),
            ...$this->formOptions(),
        ]);
    }

    public function update(PatientRequest $request, Patient $patient): RedirectResponse
    {
        $this->patients->update($patient, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ficha actualizada.']);

        return to_route('patients.show', $patient);
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        $this->patients->archive($patient);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Paciente {$patient->folio} archivado."]);

        return to_route('patients.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'sexes' => Sex::options(),
            'bloodTypes' => BloodType::options(),
        ];
    }
}
