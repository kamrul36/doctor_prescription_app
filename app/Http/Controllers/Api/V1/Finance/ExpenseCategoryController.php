<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Finance\Actions\SaveExpenseCategoryAction;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SaveExpenseCategoryRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Expense categories. No delete: deactivate with `is_active: false`. */
class ExpenseCategoryController extends Controller
{
    /** Active categories; `include_inactive=1` for all. */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Expense::class);

        $categories = ExpenseCategory::query()
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name_en')->get();

        return response()->json(['data' => $categories->map(fn (ExpenseCategory $c) => self::row($c))->all()]);
    }

    public function store(SaveExpenseCategoryRequest $request, SaveExpenseCategoryAction $save): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json(['data' => self::row($save->handle($actor, null, $request->validated()))], 201);
    }

    public function update(SaveExpenseCategoryRequest $request, ExpenseCategory $category, SaveExpenseCategoryAction $save): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json(['data' => self::row($save->handle($actor, $category, $request->validated()))]);
    }

    /** @return array{id: int, name_en: string, name_bn: string|null, is_active: bool} */
    private static function row(ExpenseCategory $c): array
    {
        return ['id' => $c->id, 'name_en' => $c->name_en, 'name_bn' => $c->name_bn, 'is_active' => $c->is_active];
    }
}
