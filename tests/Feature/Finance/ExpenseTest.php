<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/** Expenses and their categories on the web and the API (spec §4.6, §8.5). */
class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private ExpenseCategory $rent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessSeeder::class, ExpenseCategorySeeder::class]);
        $this->rent = ExpenseCategory::where('name_en', 'Rent')->firstOrFail();
    }

    public function test_starter_categories_are_seeded_once(): void
    {
        $this->assertSame(6, ExpenseCategory::count());
        $this->rent->update(['name_en' => 'House rent']);

        $this->seed(ExpenseCategorySeeder::class);

        $this->assertSame(6, ExpenseCategory::count());
        $this->assertDatabaseMissing('expense_categories', ['name_en' => 'Rent']);
    }

    public function test_assistant_records_edits_and_deletes_an_expense(): void
    {
        $assistant = User::factory()->assistant()->create();

        $this->actingAs($assistant)->post('/expenses', [
            'category_id' => $this->rent->id, 'amount' => '15000', 'spent_on' => now()->toDateString(), 'method' => 'bank', 'note' => 'November',
        ])->assertRedirect(route('expenses.index', ['month' => now()->format('Y-m')]))->assertSessionHasNoErrors();

        $expense = Expense::firstOrFail();
        $this->assertSame('15000.00', $expense->amount->toDecimal());
        $this->assertSame($assistant->id, $expense->recorded_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense.created', 'auditable_id' => $expense->id]);

        $this->get('/expenses')->assertOk()->assertSee('November')->assertSee('৳15,000.00')
            ->assertSee('x-data="validatedForm(', false)->assertSee('data-rule="money"', false)
            ->assertSee('Amount (৳) <span class="text-red-600" aria-hidden="true">*</span></label>', false);

        $this->put(route('expenses.update', $expense), [
            'category_id' => $this->rent->id, 'amount' => '14500.50', 'spent_on' => now()->toDateString(), 'method' => 'cash',
        ])->assertSessionHasNoErrors();
        $this->assertSame('14500.50', $expense->fresh()->amount->toDecimal());

        $this->delete(route('expenses.destroy', $expense))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($expense);
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense.deleted']);
    }

    public function test_wrong_values_are_rejected_on_web_and_api(): void
    {
        $user = User::factory()->doctor()->create();
        $h = ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
        $valid = ['category_id' => $this->rent->id, 'amount' => '100', 'spent_on' => now()->toDateString(), 'method' => 'cash'];

        foreach ([
            'amount' => ['amount' => '10.555'],
            'spent_on' => ['spent_on' => now()->addDay()->toDateString()],
            'method' => ['method' => 'cheque'],
            'category_id' => ['category_id' => 999999],
        ] as $field => $override) {
            $this->actingAs($user)->post('/expenses', [...$valid, ...$override])->assertSessionHasErrors($field);
            $this->postJson('/api/v1/expenses', [...$valid, ...$override], $h)->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->postJson('/api/v1/expenses', [...$valid, 'amount' => '0'], $h)->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->rent->update(['is_active' => false]);
        $this->postJson('/api/v1/expenses', $valid, $h)->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->assertSame(0, Expense::count());
    }

    public function test_an_expense_keeps_a_category_deactivated_later(): void
    {
        $user = User::factory()->doctor()->create();
        $h = ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
        $id = $this->postJson('/api/v1/expenses', ['category_id' => $this->rent->id, 'amount' => '500', 'spent_on' => now()->toDateString(), 'method' => 'cash'], $h)
            ->assertCreated()->assertJsonPath('data.amount', '500.00')->json('data.id');

        $this->rent->update(['is_active' => false]);

        $this->putJson("/api/v1/expenses/{$id}", ['category_id' => $this->rent->id, 'amount' => '600', 'spent_on' => now()->toDateString(), 'method' => 'cash'], $h)
            ->assertOk()->assertJsonPath('data.amount', '600.00');
        $this->getJson('/api/v1/expenses?month='.now()->format('Y-m'), $h)->assertOk()->assertJsonPath('data.0.id', $id);
        $this->deleteJson("/api/v1/expenses/{$id}", [], $h)->assertNoContent();
        $this->getJson('/api/v1/expenses', $h)->assertJsonCount(0, 'data');
    }

    public function test_categories_are_managed_and_unique_ignoring_case(): void
    {
        $user = User::factory()->assistant()->create();

        $this->actingAs($user)->post('/expenses/categories', ['name_en' => 'Cleaning', 'name_bn' => 'পরিষ্কার'])->assertSessionHasNoErrors();
        $this->post('/expenses/categories', ['name_en' => 'cleaning'])->assertSessionHasErrors('name_en');
        $cleaning = ExpenseCategory::where('name_en', 'Cleaning')->firstOrFail();

        $this->put(route('expenses.categories.update', $cleaning), ['name_en' => 'Cleaning', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($cleaning->fresh()->is_active);
        $this->get('/expenses/categories')->assertOk()->assertSee('Cleaning');

        $h = ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
        $this->getJson('/api/v1/expense-categories', $h)->assertOk()->assertJsonMissing(['name_en' => 'Cleaning']);
        $this->getJson('/api/v1/expense-categories?include_inactive=1', $h)->assertJsonFragment(['name_en' => 'Cleaning']);
    }

    public function test_permissions(): void
    {
        $nobody = User::factory()->create();
        $this->actingAs($nobody)->get('/expenses')->assertForbidden();
        $this->post('/expenses', [])->assertForbidden();
        $this->get('/expenses/categories')->assertForbidden();
    }
}
