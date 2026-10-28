<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ClinicSettingsRequest;
use App\Services\ClinicSettingsService;
use App\Services\Prescriptions\PrescriberProfile;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClinicSettingsController extends Controller
{
    public function __construct(private readonly ClinicSettingsService $clinic) {}

    public function edit(SettingsService $settings, PrescriberProfile $prescriber): Response
    {
        $images = [];

        foreach (ClinicSettingsRequest::IMAGES as $key => $field) {
            $images[$field] = $this->clinic->imagePath($key) !== null
                ? route('settings.clinic.image', ['image' => $field, 'v' => substr(md5((string) $settings->get($key)), 0, 8)])
                : null;
        }

        return Inertia::render('settings/Clinic', [
            'clinic' => $settings->group('clinic'),
            'doctor' => $settings->group('doctor'),
            'images' => $images,
            'missing' => $prescriber->missing(),
        ]);
    }

    public function update(ClinicSettingsRequest $request): RedirectResponse
    {
        $this->clinic->update($request->textValues(), $request->uploadedImages(), $request->imagesToRemove());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Datos del consultorio guardados.']);

        return to_route('settings.clinic.edit');
    }

    public function image(string $image): StreamedResponse
    {
        $key = array_search($image, ClinicSettingsRequest::IMAGES, true);
        $path = is_string($key) ? $this->clinic->imagePath($key) : null;

        abort_if($path === null, 404);

        return Storage::disk((string) config('medical.files_disk'))->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
