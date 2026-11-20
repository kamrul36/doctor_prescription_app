<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Finance\Reports\ReportCache;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Creates or renames an expense category; deactivating hides it from new expenses. Names are unique ignoring case. */
class SaveExpenseCategoryAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array{name_en: string, name_bn?: string|null, is_active?: bool|string|int}  $data  validated by SaveExpenseCategoryRequest */
    public function handle(User $actor, ?ExpenseCategory $category, array $data): ExpenseCategory
    {
        return DB::transaction(function () use ($category, $data) {
            $name = trim($data['name_en']);
            $taken = ExpenseCategory::query()
                ->whereRaw('LOWER(name_en) = ?', [mb_strtolower($name)])
                ->when($category !== null, fn ($q) => $q->whereKeyNot($category->id))
                ->lockForUpdate()->exists();
            if ($taken) {
                throw ValidationException::withMessages(['name_en' => __('This category already exists.')]);
            }

            $creating = $category === null;
            $category ??= new ExpenseCategory;
            $category->fill([
                'name_en' => $name,
                'name_bn' => filled($data['name_bn'] ?? null) ? trim((string) $data['name_bn']) : null,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : ($category->is_active ?? true),
            ]);
            $changed = array_keys($category->getDirty());
            $category->save();
            ReportCache::flush();

            $this->audit->log($creating ? 'expense_category.created' : 'expense_category.updated', $category, ['fields' => $changed]);

            return $category;
        });
    }
}
