<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Domain\Catalog\Actions\DeleteCatalogItemAction;
use App\Domain\Catalog\Actions\SaveCatalogItemAction;
use App\Domain\Catalog\CatalogSearch;
use App\Domain\Catalog\Models\Procedure;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveProcedureRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProcedureController extends Controller
{
    public function index(Request $request, CatalogSearch $search): View
    {
        Gate::authorize('viewAny', Procedure::class);

        /** @var User $actor */
        $actor = $request->user();
        $q = $request->string('q')->trim()->value();
        $items = $search->paginate($actor, Procedure::class, $q, $request->integer('page', 1), 25, $request->boolean('all'))
            ->withQueryString();

        return view('catalog.procedures.index', ['items' => $items, 'q' => $q, 'all' => $request->boolean('all')]);
    }

    public function create(): View
    {
        Gate::authorize('create', Procedure::class);

        return view('catalog.procedures.form', ['item' => new Procedure]);
    }

    public function store(SaveProcedureRequest $request, SaveCatalogItemAction $save): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $item = $save->handle($actor, Procedure::class, null, $request->validated());

        return redirect()->route('catalog.procedures.index')->with('status', "Procedure {$item->name_en} added.");
    }

    public function edit(Procedure $procedure): View
    {
        Gate::authorize('update', $procedure);

        return view('catalog.procedures.form', ['item' => $procedure]);
    }

    public function update(SaveProcedureRequest $request, Procedure $procedure, SaveCatalogItemAction $save): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $save->handle($actor, Procedure::class, $procedure, $request->validated());

        return redirect()->route('catalog.procedures.index')->with('status', 'Procedure saved.');
    }

    public function destroy(Procedure $procedure, DeleteCatalogItemAction $delete): RedirectResponse
    {
        Gate::authorize('delete', $procedure);

        $delete->handle($procedure);

        return redirect()->route('catalog.procedures.index')->with('status', 'Procedure deleted.');
    }
}
