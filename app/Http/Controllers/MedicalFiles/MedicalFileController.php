<?php

declare(strict_types=1);

namespace App\Http\Controllers\MedicalFiles;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicalFiles\DeleteMedicalFileRequest;
use App\Http\Requests\MedicalFiles\UploadMedicalFilesRequest;
use App\Models\MedicalFile;
use App\Models\Patient;
use App\Models\User;
use App\Services\MedicalFiles\MedicalFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicalFileController extends Controller
{
    public function __construct(private readonly MedicalFileService $files) {}

    public function store(UploadMedicalFilesRequest $request, Patient $patient): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $created = $this->files->store($patient, $request->uploadedFiles(), $request->meta(), $user);

        $count = count($created);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count === 1 ? 'Estudio guardado.' : "{$count} estudios guardados.",
        ]);

        return back();
    }

    public function show(Request $request, MedicalFile $file): StreamedResponse
    {
        return $this->files->response($file, $request->boolean('download'));
    }

    public function destroy(DeleteMedicalFileRequest $request, MedicalFile $file): RedirectResponse
    {
        $this->files->delete($file, (string) $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Estudio eliminado del expediente.']);

        return back();
    }
}
