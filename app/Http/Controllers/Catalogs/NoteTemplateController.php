<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\NoteTemplateRequest;
use App\Models\NoteTemplate;
use App\Services\Catalogs\CatalogWriter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NoteTemplateController extends Controller
{
    public function __construct(private readonly CatalogWriter $writer) {}

    public function index(): Response
    {
        return Inertia::render('catalogs/Templates', [
            'templates' => NoteTemplate::query()->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'structure', 'is_active']),
        ]);
    }

    public function store(NoteTemplateRequest $request): RedirectResponse
    {
        $this->writer->save(new NoteTemplate, $request->templateData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plantilla creada.']);

        return back();
    }

    public function update(NoteTemplateRequest $request, NoteTemplate $template): RedirectResponse
    {
        $this->writer->save($template, $request->templateData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plantilla actualizada.']);

        return back();
    }

    /**
     * Las consultas que la usaron conservan su texto; solo pierden la referencia.
     */
    public function destroy(NoteTemplate $template): RedirectResponse
    {
        $this->writer->delete($template);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plantilla eliminada.']);

        return back();
    }
}
