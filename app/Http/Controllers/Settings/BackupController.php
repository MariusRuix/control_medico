<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Backups\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(): Response
    {
        $directory = $this->backups->directory();

        return Inertia::render('settings/Backups', [
            'status' => $this->backups->status(),
            'backups' => $this->backups->list(),
            'config' => [
                'path' => $directory,
                'inside_project' => Str::startsWith(
                    str_replace('\\', '/', strtolower($directory)),
                    str_replace('\\', '/', strtolower(base_path())),
                ),
                'encrypted' => $this->backups->encrypted(),
                'keep_days' => (int) config('backup.keep_days'),
                'time' => (string) config('backup.time'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        @set_time_limit(900);

        try {
            $result = $this->backups->run($user);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['backup' => 'El respaldo falló: '.$e->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Respaldo creado ('.Number::fileSize($result['size'], precision: 1).').',
        ]);

        return to_route('settings.backups.index');
    }

    public function download(string $name, AuditLogger $audit): BinaryFileResponse
    {
        $path = $this->backups->pathFor($name);

        abort_if($path === null, 404);

        $audit->log('backup_downloaded', null, [], ['file' => $name]);

        return response()->download($path, $name, ['Content-Type' => 'application/zip']);
    }
}
