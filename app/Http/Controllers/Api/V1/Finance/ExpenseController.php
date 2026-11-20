<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Finance\Actions\DeleteExpenseAction;
use App\Domain\Finance\Actions\SaveExpenseAction;
use App\Domain\Finance\ExpenseListQuery;
use App\Domain\Finance\Models\Expense;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SaveExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Expenses (`expenses.write`). Deleting is a soft delete; deleted expenses leave the reports. */
class ExpenseController extends Controller
{
    /** Filters: `month` (`YYYY-MM`, default this month), `category_id`. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Expense::class);

        return ExpenseResource::collection(
            ExpenseListQuery::build($request->only(['month', 'category_id']))->paginate(min(max($request->integer('per_page', 50), 1), 200)),
        );
    }

    public function store(SaveExpenseRequest $request, SaveExpenseAction $save): JsonResponse
    {
        return (new ExpenseResource($save->handle($this->actor($request), null, $request->validated())->load('category')))->response()->setStatusCode(201);
    }

    public function show(Expense $expense): ExpenseResource
    {
        Gate::authorize('viewAny', Expense::class);

        return new ExpenseResource($expense->load('category'));
    }

    public function update(SaveExpenseRequest $request, Expense $expense, SaveExpenseAction $save): ExpenseResource
    {
        return new ExpenseResource($save->handle($this->actor($request), $expense, $request->validated())->load('category'));
    }

    public function destroy(Request $request, Expense $expense, DeleteExpenseAction $delete): Response
    {
        Gate::authorize('delete', $expense);
        $delete->handle($this->actor($request), $expense);

        return response()->noContent();
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
