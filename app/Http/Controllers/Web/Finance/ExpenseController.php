<?php

namespace App\Http\Controllers\Web\Finance;

use App\Domain\Finance\Actions\DeleteExpenseAction;
use App\Domain\Finance\Actions\SaveExpenseAction;
use App\Domain\Finance\ExpenseListQuery;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Finance\Reports\Sum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SaveExpenseRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Expenses of a month, with the form to record one (web). Same Actions and FormRequest as the API. */
class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Expense::class);

        $month = ExpenseListQuery::month($request->string('month')->value() ?: null);
        $expenses = ExpenseListQuery::build(['month' => $month->format('Y-m'), 'category_id' => $request->input('category_id')])->get();

        return view('expenses.index', [
            'expenses' => $expenses,
            'total' => Sum::of($expenses->pluck('amount')),
            'month' => $month,
            'categoryId' => $request->integer('category_id') ?: null,
            'categories' => ExpenseCategory::query()->orderBy('name_en')->get(),
            'expense' => new Expense(['spent_on' => now()->toDateString(), 'method' => 'cash']),
        ]);
    }

    public function store(SaveExpenseRequest $request, SaveExpenseAction $save): RedirectResponse
    {
        $expense = $save->handle($this->actor($request), null, $request->validated());

        return redirect()->route('expenses.index', ['month' => $expense->spent_on->format('Y-m')])->with('success', __('Expense recorded.'));
    }

    public function edit(Expense $expense): View
    {
        Gate::authorize('update', $expense);

        return view('expenses.edit', [
            'expense' => $expense,
            'categories' => ExpenseCategory::query()->where('is_active', true)->orWhere('id', $expense->category_id)->orderBy('name_en')->get(),
        ]);
    }

    public function update(SaveExpenseRequest $request, Expense $expense, SaveExpenseAction $save): RedirectResponse
    {
        $expense = $save->handle($this->actor($request), $expense, $request->validated());

        return redirect()->route('expenses.index', ['month' => $expense->spent_on->format('Y-m')])->with('success', __('Expense saved.'));
    }

    public function destroy(Request $request, Expense $expense, DeleteExpenseAction $delete): RedirectResponse
    {
        Gate::authorize('delete', $expense);
        $delete->handle($this->actor($request), $expense);

        return redirect()->route('expenses.index', ['month' => $expense->spent_on->format('Y-m')])->with('success', __('Expense deleted.'));
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
