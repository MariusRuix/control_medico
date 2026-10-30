<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\CatalogIndexRequest;
use App\Http\Requests\Catalogs\MedicationRequest;
use App\Models\Medication;
use App\Services\Catalogs\CatalogWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;

class MedicationController extends Controller
{
    private const int PER_PAGE = 20;

    public function __construct(private readonly CatalogWriter $writer) {}

    public function index(CatalogIndexRequest $request): Response
    {
        $search = $request->search();
        $filter = $request->filter();

        $medications = Medication::query()
            ->when($search !== null, function (Builder $q) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $search).'%';
                $q->where(fn (Builder $w) => $w->where('generic_name', 'like', $like)->orWhere('brand_name', 'like', $like));
            })
            ->when($filter === 'favorites', fn (Builder $q) => $q->where('is_favorite', true)->where('is_active', true))
            ->when($filter === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->when($filter === 'all', fn (Builder $q) => $q->where('is_active', true))
            ->orderByDesc('is_favorite')
            ->orderBy('generic_name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('catalogs/Medications', [
            'medications' => JsonResource::collection($medications),
            'filters' => ['q' => $search, 'filter' => $filter],
        ]);
    }

    public function store(MedicationRequest $request): RedirectResponse
    {
        $this->writer->save(new Medication, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Medicamento agregado.']);

        return back();
    }

    public function update(MedicationRequest $request, Medication $medication): RedirectResponse
    {
        $this->writer->save($medication, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Medicamento actualizado.']);

        return back();
    }

    /**
     * Marcar / desmarcar como favorito (vademécum) con un clic.
     */
    public function favorite(Medication $medication): RedirectResponse
    {
        $this->writer->save($medication, ['is_favorite' => ! $medication->is_favorite]);

        return back();
    }
}
