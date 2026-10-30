<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\ServiceRequest;
use App\Models\Service;
use App\Services\Catalogs\CatalogWriter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function __construct(private readonly CatalogWriter $writer) {}

    public function index(): Response
    {
        return Inertia::render('catalogs/Services', [
            'services' => Service::query()
                ->withCount('appointments')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'colors' => ServiceRequest::COLORS,
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $this->writer->save(new Service, $this->data($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Servicio agregado.']);

        return back();
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $this->writer->save($service, $this->data($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Servicio actualizado.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function data(ServiceRequest $request): array
    {
        $data = $request->validated();

        if (! $data['is_appointable']) {
            $data['duration_minutes'] = $data['duration_minutes'] ?? null;
        }

        return $data;
    }
}
