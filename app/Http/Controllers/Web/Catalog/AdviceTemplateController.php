<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Domain\Catalog\Actions\DeleteCatalogItemAction;
use App\Domain\Catalog\Actions\SaveCatalogItemAction;
use App\Domain\Catalog\CatalogSearch;
use App\Domain\Catalog\Models\AdviceTemplate;
use App\Domain\Practice\Models\PrescriptionTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SaveAdviceTemplateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdviceTemplateController extends Controller
{
    public function index(Request $request, CatalogSearch $search): View
    {
        Gate::authorize('viewAny', AdviceTemplate::class);

        /** @var User $actor */
        $actor = $request->user();
        $q = $request->string('q')->trim()->value();
        $specialty = $request->string('specialty')->trim()->value() ?: null;
        $items = $search->paginate($actor, AdviceTemplate::class, $q, $request->integer('page', 1), 25, $request->boolean('all'), $specialty)
            ->withQueryString();

        return view('catalog.advice-templates.index', ['items' => $items, 'q' => $q, 'all' => $request->boolean('all'), 'specialty' => $specialty]);
    }

    public function create(): View
    {
        Gate::authorize('create', AdviceTemplate::class);

        return view('catalog.advice-templates.form', $this->formData(new AdviceTemplate));
    }

    public function store(SaveAdviceTemplateRequest $request, SaveCatalogItemAction $save): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $item = $save->handle($actor, AdviceTemplate::class, null, $request->validated());

        return redirect()->route('catalog.advice-templates.index')->with('status', "Advice template {$item->title} added.");
    }

    public function edit(AdviceTemplate $adviceTemplate): View
    {
        Gate::authorize('update', $adviceTemplate);

        return view('catalog.advice-templates.form', $this->formData($adviceTemplate));
    }

    public function update(SaveAdviceTemplateRequest $request, AdviceTemplate $adviceTemplate, SaveCatalogItemAction $save): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $save->handle($actor, AdviceTemplate::class, $adviceTemplate, $request->validated());

        return redirect()->route('catalog.advice-templates.index')->with('status', 'Advice template saved.');
    }

    public function destroy(AdviceTemplate $adviceTemplate, DeleteCatalogItemAction $delete): RedirectResponse
    {
        Gate::authorize('delete', $adviceTemplate);

        $delete->handle($adviceTemplate);

        return redirect()->route('catalog.advice-templates.index')->with('status', 'Advice template deleted.');
    }

    /** @return array<string, mixed> */
    private function formData(AdviceTemplate $item): array
    {
        return [
            'item' => $item,
            'templates' => PrescriptionTemplate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'doctor_id']),
        ];
    }
}
