<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payment;
use App\Domain\Finance\Reports\CashBookReport;
use App\Domain\Finance\Reports\MonthlySummaryReport;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\PracticeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/** The four reports (spec §8.4) and the P1.7 exit check: the monthly report equals the cash book. */
class ReportTest extends TestCase
{
    use FinanceTestHelpers;
    use RefreshDatabase;

    private User $doctorUser;

    private User $assistant;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->seed([AccessSeeder::class, PracticeSeeder::class, ExpenseCategorySeeder::class]);
        $this->assistant = User::factory()->assistant()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_monthly_summary_equals_the_cash_book_with_voids_and_deletes_excluded(): void
    {
        $this->books();

        $summary = MonthlySummaryReport::run(2026, 11);
        $book = CashBookReport::run(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));

        $this->assertSame('1100.00', $summary['income']->toDecimal(), '500 + 300 + 300 in November; the voided 200 does not count');
        $this->assertSame('250.00', $summary['expenses']->toDecimal(), 'the deleted expense does not count');
        $this->assertSame('850.00', $summary['net']->toDecimal());
        $this->assertSame($summary['income']->toDecimal(), $book['money_in']->toDecimal());
        $this->assertSame($summary['expenses']->toDecimal(), $book['money_out']->toDecimal());
        $this->assertSame($summary['net']->toDecimal(), $book['closing']->subtract($book['opening'])->toDecimal());

        // October's money is the opening balance: 400 in, 100 out.
        $this->assertSame('300.00', $book['opening']->toDecimal());
        $this->assertSame('1150.00', $book['closing']->toDecimal());

        $sumOfDays = array_reduce($summary['daily'], fn ($c, $d) => $c + $d['income']->minor() - $d['expenses']->minor(), 0);
        $this->assertSame($summary['net']->minor(), $sumOfDays);
        $this->assertSame(['new' => '800.00', 'follow_up' => '300.00'], collect($summary['income_by_visit_type'])->mapWithKeys(fn ($r) => [$r['visit_type']->value => $r['amount']->toDecimal()])->all());
    }

    public function test_daily_collection_counts_todays_money_by_method(): void
    {
        $this->books();

        $this->actingAs($this->assistant)->get('/reports/daily-collection?date=2026-11-20')->assertOk()
            ->assertSee('Daily collection')->assertSee('৳600.00')->assertSee('bKash');

        $h = ['Authorization' => 'Bearer '.JWTAuth::fromUser($this->assistant)];
        $this->getJson('/api/v1/reports/daily-collection?date=2026-11-20', $h)->assertOk()
            ->assertJsonPath('data.collected', '600.00')
            ->assertJsonPath('data.by_method.0.method', 'cash')
            ->assertJsonPath('data.by_method.1.method', 'bkash')
            ->assertJsonCount(2, 'data.payments');
    }

    public function test_dues_are_grouped_per_patient_and_aged(): void
    {
        [$user, , $chamber] = $this->doctorWithFees();
        Carbon::setTestNow('2026-10-01 09:00:00');
        $old = $this->invoiceOf($this->saveVisit($user, $chamber));
        Carbon::setTestNow('2026-11-15 09:00:00');
        $recent = $this->invoiceOf($this->saveVisit($user, $chamber, ['visit_type' => 'follow_up']));
        $this->pay($recent, '100');
        Carbon::setTestNow('2026-11-20 10:00:00');

        $h = ['Authorization' => 'Bearer '.JWTAuth::fromUser($this->assistant)];
        $res = $this->getJson('/api/v1/reports/dues', $h)->assertOk()
            ->assertJsonPath('data.total', '700.00')
            ->assertJsonPath('data.buckets.0_7', '200.00')
            ->assertJsonPath('data.buckets.30_plus', '500.00')
            ->assertJsonPath('data.patients.0.due', '500.00');
        $this->assertSame(50, $res->json('data.patients.0.invoices.0.age_days'));

        $this->actingAs($this->assistant)->get('/reports/dues')->assertOk()->assertSee($old->invoice_no)->assertSee('৳700.00');
    }

    public function test_cash_book_page_and_csv(): void
    {
        $this->books();
        $this->actingAs($this->doctorUser);

        $this->get('/reports/cash-book?from=2026-11-01&to=2026-11-30')->assertOk()
            ->assertSee('Opening balance')->assertSee('৳300.00')->assertSee('৳1,150.00')->assertSee('Rent');

        $csv = $this->get('/reports/cash-book?from=2026-11-01&to=2026-11-30&format=csv');
        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $csv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('1150.00', $body);

        $this->get('/reports/monthly-summary?month=2026-11')->assertOk()->assertSee('৳850.00');
        $this->get('/reports/monthly-summary?month=2026-11&format=csv')->assertOk();
        $this->get('/reports/cash-book?from=2026-11-10&to=2026-11-01')->assertSessionHasErrors('to');
    }

    public function test_closed_months_are_cached_but_a_late_void_shows_at_once(): void
    {
        $this->books();
        $this->assertSame('400.00', MonthlySummaryReport::run(2026, 10)['income']->toDecimal());

        $october = Payment::query()->where('amount', '400.00')->firstOrFail();
        $this->actingAs($this->doctorUser)->post(route('payments.void', $october), ['reason' => 'Bounced'])->assertSessionHasNoErrors();

        $this->assertSame('0.00', MonthlySummaryReport::run(2026, 10)['income']->toDecimal());
    }

    public function test_who_sees_which_report(): void
    {
        $this->actingAs($this->assistant)->get('/reports')->assertOk()->assertSee('Daily collection')->assertSee('Dues')->assertDontSee('Cash book');
        $this->get('/reports/monthly-summary')->assertForbidden();
        $this->get('/reports/cash-book')->assertForbidden();
        $this->get('/reports/nope')->assertNotFound();

        $h = ['Authorization' => 'Bearer '.JWTAuth::fromUser($this->assistant)];
        $this->getJson('/api/v1/reports/cash-book', $h)->assertForbidden();

        $doctor = User::factory()->doctor()->create();
        $this->actingAs($doctor)->get('/reports')->assertSee('Cash book')->assertSee('Monthly summary');

        $this->actingAs(User::factory()->create())->get('/reports')->assertForbidden();
    }

    /**
     * October: one ৳400 payment and a ৳100 expense. November: visits paid 500 (cash) and 300 (follow-up,
     * bKash, today) and 300 (cash, today); a ৳200 payment voided; expenses 150 + 100, and a deleted 999.
     */
    private function books(): void
    {
        [$this->doctorUser, , $chamber] = $this->doctorWithFees(['new' => '500', 'follow_up' => '300']);
        $rent = ExpenseCategory::where('name_en', 'Rent')->firstOrFail();
        $other = ExpenseCategory::where('name_en', 'Other')->firstOrFail();

        Carbon::setTestNow('2026-10-15 11:00:00');
        $oct = $this->invoiceOf($this->saveVisit($this->doctorUser, $chamber));
        $this->pay($oct, '400');
        $this->expense($other, '100', '2026-10-15');

        Carbon::setTestNow('2026-11-05 11:00:00');
        $a = $this->invoiceOf($this->saveVisit($this->doctorUser, $chamber));
        $this->pay($a, '500');
        $this->expense($rent, '150', '2026-11-05');
        $deleted = $this->expense($other, '999', '2026-11-05');
        $this->actingAs($this->assistant)->delete(route('expenses.destroy', $deleted));

        Carbon::setTestNow('2026-11-20 09:00:00');
        $b = $this->invoiceOf($this->saveVisit($this->doctorUser, $chamber, ['visit_type' => 'follow_up']));
        $this->pay($b, '300', 'bkash');
        $c = $this->invoiceOf($this->saveVisit($this->doctorUser, $chamber));
        $voided = $this->pay($c, '200');
        $this->actingAs($this->doctorUser)->post(route('payments.void', $voided), ['reason' => 'Mistake']);
        $this->pay($c, '300');
        $this->expense($other, '100', '2026-11-20');

        Carbon::setTestNow('2026-11-20 10:00:00');
    }

    private function pay(Invoice $invoice, string $amount, string $method = 'cash'): Payment
    {
        $this->actingAs($this->assistant)->post(route('invoices.payments.store', $invoice), [
            'amount' => $amount, 'method' => $method, 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        return Payment::query()->latest('id')->firstOrFail();
    }

    private function expense(ExpenseCategory $category, string $amount, string $date): Expense
    {
        $this->actingAs($this->assistant)->post('/expenses', [
            'category_id' => $category->id, 'amount' => $amount, 'spent_on' => $date, 'method' => 'cash',
        ])->assertSessionHasNoErrors();

        return Expense::query()->latest('id')->firstOrFail();
    }
}
