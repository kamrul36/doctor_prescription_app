<?php

namespace App\Http\Controllers\Web\Finance;

use App\Domain\Finance\Actions\SaveExpenseCategoryAction;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SaveExpenseCategoryRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Expense categories (web): list with an add form; each row can be renamed or deactivated. */
class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manageCategories', Expense::class);

        return view('expenses.categories', ['categories' => ExpenseCategory::query()->withCount('expenses')->orderBy('name_en')->get()]);
    }

    public function store(SaveExpenseCategoryRequest $request, SaveExpenseCategoryAction $save): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $save->handle($actor, null, $request->validated());

        return redirect()->route('expenses.categories.index')->with('success', __('Category saved.'));
    }

    public function update(SaveExpenseCategoryRequest $request, ExpenseCategory $category, SaveExpenseCategoryAction $save): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $save->handle($actor, $category, $request->validated());

        return redirect()->route('expenses.categories.index')->with('success', __('Category saved.'));
    }
}
