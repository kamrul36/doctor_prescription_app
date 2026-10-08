<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Domain\Catalog\Actions\DeleteCatalogItemAction;
use App\Domain\Catalog\Actions\SaveCatalogItemAction;
use App\Domain\Catalog\CatalogSearch;
use App\Domain\Catalog\Models\LabTest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveLabTestRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LabTestController extends Controller
{
    public function index(Request $request, CatalogSearch $search): View
    {
        Gate::authorize('viewAny', LabTest::class);

        /** @var User $actor */
        $actor = $request->user();
        $q = $request->string('q')->trim()->value();
        $items = $search->paginate($actor, LabTest::class, $q, $request->integer('page', 1), 25, $request->boolean('all'))
            ->withQueryString();

        return view('catalog.lab-tests.index', ['items' => $items, 'q' => $q, 'all' => $request->boolean('all')]);
    }

    public function create(): View
    {
        Gate::authorize('create', LabTest::class);

        return view('catalog.lab-tests.form', ['item' => new LabTest]);
    }

    public function store(SaveLabTestRequest $request, SaveCatalogItemAction $save): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $item = $save->handle($actor, LabTest::class, null, $request->validated());

        return redirect()->route('catalog.lab-tests.index')->with('status', "Lab test {$item->name} added.");
    }

    public function edit(LabTest $labTest): View
    {
        Gate::authorize('update', $labTest);

        return view('catalog.lab-tests.form', ['item' => $labTest]);
    }

    public function update(SaveLabTestRequest $request, LabTest $labTest, SaveCatalogItemAction $save): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $save->handle($actor, LabTest::class, $labTest, $request->validated());

        return redirect()->route('catalog.lab-tests.index')->with('status', 'Lab test saved.');
    }

    public function destroy(LabTest $labTest, DeleteCatalogItemAction $delete): RedirectResponse
    {
        Gate::authorize('delete', $labTest);

        $delete->handle($labTest);

        return redirect()->route('catalog.lab-tests.index')->with('status', 'Lab test deleted.');
    }
}
