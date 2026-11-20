<?php

namespace Tests\Feature\Finance;

use App\Domain\Audit\AuditLog;
use App\Domain\Finance\InvoiceStatus;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payment;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\PracticeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Payments, discounts, voids, prices and receipts at the desk (web), spec §8. */
class PaymentTest extends TestCase
{
    use FinanceTestHelpers;
    use RefreshDatabase;

    private User $doctorUser;

    private User $assistant;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessSeeder::class, PracticeSeeder::class]);
        [$this->doctorUser, , $chamber] = $this->doctorWithFees();
        $this->invoice = $this->invoiceOf($this->saveVisit($this->doctorUser, $chamber)); // ৳500
        $this->assistant = User::factory()->assistant()->create();
    }

    public function test_partial_then_full_payment_updates_status_and_due(): void
    {
        $this->pay('200', key: 'k-first-0001')->assertRedirect(route('invoices.show', $this->invoice))
            ->assertSessionHas('success', fn ($s) => str_contains($s, 'RC-'));
        $invoice = $this->invoice->fresh();
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertSame('300.00', $invoice->due->toDecimal());

        $this->pay('300', 'bkash', key: 'k-second-0002', reference: 'TX123');
        $invoice = $this->invoice->fresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('500.00', $invoice->paid->toDecimal());
        $this->assertSame('0.00', $invoice->due->toDecimal());

        $payment = Payment::query()->where('reference', 'TX123')->firstOrFail();
        $this->assertMatchesRegularExpression('/^RC-\d{4}-\d{6}$/', $payment->receipt_no);
        $this->assertSame($this->assistant->id, $payment->received_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.recorded', 'auditable_id' => $payment->id]);
    }

    public function test_double_submit_with_the_same_key_makes_one_payment(): void
    {
        $this->pay('200', key: 'same-key-123');
        $this->pay('200', key: 'same-key-123')->assertSessionHas('success', fn ($s) => str_contains($s, 'already recorded'));

        $this->assertSame(1, Payment::count());
        $this->assertSame('300.00', $this->invoice->fresh()->due->toDecimal());
    }

    public function test_the_same_key_for_a_different_payment_is_refused(): void
    {
        $this->pay('200', key: 'same-key-123');
        $this->pay('250', key: 'same-key-123')->assertSessionHasErrors('idempotency_key');

        $this->assertSame(1, Payment::count());
    }

    public function test_keys_are_scoped_per_user(): void
    {
        $this->pay('100', key: 'shared-key-1');
        $other = User::factory()->assistant()->create();
        $this->actingAs($other)->post(route('invoices.payments.store', $this->invoice), ['amount' => '100', 'method' => 'cash', 'idempotency_key' => 'shared-key-1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Payment::count());
    }

    public function test_overpayment_and_bad_amounts_are_refused(): void
    {
        $this->pay('500.01')->assertSessionHasErrors(['amount' => 'The amount is more than the ৳500.00 due.']);
        $this->pay('0')->assertSessionHasErrors('amount');
        $this->pay('12.345')->assertSessionHasErrors('amount');
        $this->pay('abc')->assertSessionHasErrors('amount');
        $this->actingAs($this->assistant)->post(route('invoices.payments.store', $this->invoice), ['amount' => '10', 'method' => 'cheque', 'idempotency_key' => 'abcdefgh1'])
            ->assertSessionHasErrors('method');
        $this->actingAs($this->assistant)->post(route('invoices.payments.store', $this->invoice), ['amount' => '10', 'method' => 'cash'])
            ->assertSessionHasErrors('idempotency_key');

        $this->assertSame(0, Payment::count());
    }

    public function test_voiding_a_payment_reopens_the_bill(): void
    {
        $this->pay('500');
        $payment = Payment::firstOrFail();

        $this->actingAs($this->assistant)->post(route('payments.void', $payment), ['reason' => 'Wrong patient'])->assertForbidden();

        $this->actingAs($this->doctorUser)->post(route('payments.void', $payment), ['reason' => 'Wrong patient'])->assertSessionHasNoErrors();
        $payment->refresh();
        $this->assertNotNull($payment->voided_at);
        $this->assertSame('Wrong patient', $payment->void_reason);
        $invoice = $this->invoice->fresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('500.00', $invoice->due->toDecimal());
        $this->assertSame(1, Payment::count(), 'voided, not deleted');

        $this->post(route('payments.void', $payment), ['reason' => 'Again'])->assertForbidden();
        $this->post(route('payments.void', Payment::firstOrFail()), [])->assertSessionHasErrors('reason');
    }

    public function test_voiding_an_invoice_needs_no_active_payment(): void
    {
        $this->pay('100');
        $this->actingAs($this->doctorUser)->post(route('invoices.void', $this->invoice), ['reason' => 'Duplicate'])->assertSessionHasErrors('status');

        $this->post(route('payments.void', Payment::firstOrFail()), ['reason' => 'Refunded']);
        $this->post(route('invoices.void', $this->invoice), ['reason' => 'Duplicate'])->assertSessionHasNoErrors();

        $invoice = $this->invoice->fresh();
        $this->assertSame(InvoiceStatus::Voided, $invoice->status);
        $this->pay('100')->assertForbidden();
    }

    public function test_discount_needs_permission_reason_and_cap(): void
    {
        $url = route('invoices.discount', $this->invoice);

        $this->actingAs($this->assistant)->post($url, ['discount' => '100', 'discount_reason' => 'Poor'])->assertForbidden();

        $this->actingAs($this->doctorUser)->post($url, ['discount' => '100'])->assertSessionHasErrors('discount_reason');
        $this->post($url, ['discount' => '600', 'discount_reason' => 'Too much'])->assertSessionHasErrors('discount');
        $this->post($url, ['discount' => '100', 'discount_reason' => 'Poor patient'])->assertSessionHasNoErrors();

        $invoice = $this->invoice->fresh();
        $this->assertSame('100.00', $invoice->discount->toDecimal());
        $this->assertSame('400.00', $invoice->total->toDecimal());
        $this->assertSame('400.00', $invoice->due->toDecimal());

        // Never below what is paid.
        $this->pay('300');
        $this->actingAs($this->doctorUser)->post($url, ['discount' => '250', 'discount_reason' => 'More'])->assertSessionHasErrors('discount');
        $this->post($url, ['discount' => '0'])->assertSessionHasNoErrors();
        $this->assertNull($this->invoice->fresh()->discount_reason);
    }

    public function test_a_failed_form_shows_its_error_only_on_its_own_card(): void
    {
        $this->actingAs($this->doctorUser)->from(route('invoices.show', $this->invoice))
            ->post(route('invoices.discount', $this->invoice), ['discount' => '600', 'discount_reason' => 'Too much']);

        $html = $this->get(route('invoices.show', $this->invoice))->assertOk()->getContent();
        $message = 'The discount cannot be more than the bill';
        // Once in the discount card's errors, once in the error toast; not in the payment, details or void cards.
        $this->assertSame(2, substr_count($html, $message));
    }

    public function test_a_line_price_can_change_only_before_payment(): void
    {
        $line = $this->invoice->lines->first();
        $url = route('invoices.lines.update', [$this->invoice, $line->id]);

        $this->actingAs($this->assistant)->put($url, ['unit_price' => '700'])->assertForbidden();
        $this->actingAs($this->doctorUser)->put($url, ['unit_price' => '700'])->assertSessionHasNoErrors();
        $this->assertSame('700.00', $this->invoice->fresh()->total->toDecimal());

        $this->pay('100');
        $this->actingAs($this->doctorUser)->put($url, ['unit_price' => '800'])->assertSessionHasErrors('unit_price');
        $this->assertSame('700.00', $this->invoice->fresh()->total->toDecimal());
    }

    public function test_receipt_details_are_saved_by_the_desk(): void
    {
        $this->actingAs($this->assistant)->patch(route('invoices.update', $this->invoice), ['referred_by' => 'Dr. Rahman', 'note' => 'Paid at desk'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Dr. Rahman', $this->invoice->fresh()->referred_by);
        $this->patch(route('invoices.update', $this->invoice), ['under_doctor_id' => 999999])->assertSessionHasErrors('under_doctor_id');
    }

    public function test_receipt_prints_in_english_and_bangla_with_the_stamp(): void
    {
        $this->actingAs($this->assistant)->patch(route('invoices.update', $this->invoice), ['referred_by' => 'Dr. Rahman']);
        $this->pay('200');
        $payment = Payment::firstOrFail();

        $this->get(route('payments.receipt', [$payment, 'lang' => 'en']))->assertOk()
            ->assertSee('Money receipt')->assertSee($payment->receipt_no)->assertSee($this->invoice->invoice_no)
            ->assertSee('Dr. Rahman')->assertSee('CONS')->assertSee('৳500.00')->assertSee('৳200.00')->assertSee('PARTIAL')
            ->assertSee('size: A5', false);

        $this->get(route('payments.receipt', [$payment, 'lang' => 'bn']))->assertOk()
            ->assertSee('মানি রিসিট')->assertSee('৳৫০০.০০')->assertSee('আংশিক');

        $this->pay('300');
        $this->get(route('payments.receipt', [$payment, 'lang' => 'en']))->assertSee('PAID');
    }

    public function test_invoice_pages_and_permissions(): void
    {
        $this->actingAs($this->assistant)->get('/invoices')->assertOk()->assertSee($this->invoice->invoice_no);
        $this->get(route('invoices.show', $this->invoice))->assertOk()
            ->assertSee('Take payment')->assertSee('name="idempotency_key"', false)
            ->assertDontSee('Save discount')->assertDontSee('Void invoice');

        // The forms mirror their server rules in the browser (spec §10.1).
        $this->actingAs($this->doctorUser)->get(route('invoices.show', $this->invoice))->assertOk()
            ->assertSee('Save discount')->assertSee('Void invoice')
            ->assertSee('x-data="validatedForm(', false)->assertSee('x-show="otherErrors.length"', false)
            ->assertSee('data-rule="money"', false)->assertSee('x-text="err(\'amount\')"', false)
            ->assertSee('x-text="err(\'discount_reason\')"', false)
            ->assertSee('Amount (৳) <span class="text-red-600" aria-hidden="true">*</span></label>', false);

        $this->get('/invoices?q='.$this->invoice->patient->code)->assertSee($this->invoice->invoice_no);
        $this->get('/invoices?status=paid')->assertDontSee($this->invoice->invoice_no);

        $nobody = User::factory()->create();
        $this->actingAs($nobody)->get('/invoices')->assertForbidden();
        $this->get(route('invoices.show', $this->invoice))->assertForbidden();
    }

    public function test_audit_entries_hold_no_clinical_text(): void
    {
        $this->pay('100');
        $this->actingAs($this->doctorUser)->post(route('invoices.discount', $this->invoice), ['discount' => '50', 'discount_reason' => 'Poor patient']);

        $json = AuditLog::query()->where('action', 'like', 'invoice.%')->orWhere('action', 'like', 'payment.%')->pluck('properties')->toJson();
        $this->assertStringNotContainsString('Gingivitis', $json);
        $this->assertStringNotContainsString('Poor patient', $json);
    }

    private function pay(string $amount, string $method = 'cash', ?string $key = null, ?string $reference = null): TestResponse
    {
        return $this->actingAs($this->assistant)->post(route('invoices.payments.store', $this->invoice), array_filter([
            'amount' => $amount, 'method' => $method, 'reference' => $reference, 'idempotency_key' => $key ?? (string) Str::uuid(),
        ]));
    }
}
