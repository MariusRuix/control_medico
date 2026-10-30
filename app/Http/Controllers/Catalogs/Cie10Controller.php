<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogs\CatalogIndexRequest;
use App\Http\Requests\Catalogs\Cie10ImportRequest;
use App\Models\Cie10Code;
use App\Services\Catalogs\CatalogWriter;
use App\Services\Catalogs\Cie10Importer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class Cie10Controller extends Controller
{
    private const int PER_PAGE = 25;

    public function index(CatalogIndexRequest $request): Response
    {
        $search = $request->search();
        $inactive = $request->filter() === 'inactive';

        $codes = Cie10Code::query()
            ->where('is_active', ! $inactive)
            ->when($search !== null, function (Builder $q) use ($search): void {
                $like = str_replace(['%', '_'], ['\%', '\_'], (string) $search);
                $q->where(fn (Builder $w) => $w->where('code', 'like', $like.'%')->orWhere('description', 'like', '%'.$like.'%'));
            })
            ->orderBy('code')
            ->paginate(self::PER_PAGE, ['id', 'code', 'description', 'chapter', 'is_active'])
            ->withQueryString();

        return Inertia::render('catalogs/Cie10', [
            'codes' => JsonResource::collection($codes),
            'filters' => ['q' => $search, 'filter' => $inactive ? 'inactive' : 'all'],
            'totals' => [
                'active' => Cie10Code::query()->where('is_active', true)->count(),
                'inactive' => Cie10Code::query()->where('is_active', false)->count(),
            ],
        ]);
    }

    public function import(Cie10ImportRequest $request, Cie10Importer $importer): RedirectResponse
    {
        try {
            $result = $importer->import((string) $request->file('file')?->getRealPath());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "CIE-10: {$result['imported']} códigos importados o actualizados".($result['skipped'] ? ", {$result['skipped']} filas omitidas." : '.'),
        ]);

        return back();
    }

    /**
     * Ocultar o mostrar un código en el autocompletado.
     */
    public function toggle(Cie10Code $code, CatalogWriter $writer): RedirectResponse
    {
        $writer->save($code, ['is_active' => ! $code->is_active]);

        return back();
    }
}
