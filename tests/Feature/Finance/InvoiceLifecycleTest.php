<?php

namespace Tests\Feature\Finance;

use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Finance\InvoiceLineKind;
use App\Domain\Finance\InvoiceStatus;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payment;
use App\Domain\Patient\Models\Patient;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\PracticeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The invoice follows the visit (spec §3 item 8, §7.3, §7.5, §8.2, §8.3). */
class InvoiceLifecycleTest extends TestCase
{
    use FinanceTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessSeeder::class, PracticeSeeder::class]);
    }

    public function test_saving_a_visit_opens_an_invoice_with_the_consultation_fee(): void
    {
        [$user, , $chamber] = $this->doctorWithFees();

        $case = $this->saveVisit($user, $chamber);
        $invoice = $this->invoiceOf($case);

        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{6}$/', $invoice->invoice_no);
        $this->assertSame($case->patient_id, $invoice->patient_id);
        $this->assertSame($case->doctor_id, $invoice->under_doctor_id);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('500.00', $invoice->total->toDecimal());
        $this->assertSame('500.00', $invoice->due->toDecimal());
        $this->assertCount(1, $invoice->lines);
        $this->assertSame(InvoiceLineKind::Consultation, $invoice->lines[0]->kind);
        $this->assertSame('Consultation · New patient', $invoice->lines[0]->description_en);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.opened', 'auditable_id' => $invoice->id]);

        // Saving the draft again keeps the one invoice.
        $this->put(route('prescriptions.update', $case), ['chamber_id' => $chamber->id, 'visit_type' => 'new', 'diagnosis' => 'Changed', 'action' => 'draft']);
        $this->assertSame(1, Invoice::count());
    }

    public function test_a_missing_fee_bills_zero_and_warns(): void
    {
        [$user, , $chamber] = $this->doctorWithFees([]);

        $response = $this->actingAs($user)->post('/prescriptions', [
            'chamber_id' => $chamber->id, 'visit_type' => 'new',
            'patient' => ['name' => 'Rahim', 'age_years' => '30', 'gender' => 'male'], 'action' => 'draft',
        ]);

        $response->assertSessionHas('warning', fn ($w) => str_contains($w, 'No fee is set'));
        $invoice = $this->invoiceOf(CaseHistory::firstOrFail());
        $this->assertSame('0.00', $invoice->total->toDecimal());
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
    }

    public function test_a_free_visit_is_a_zero_invoice_without_a_warning(): void
    {
        [$user, , $chamber] = $this->doctorWithFees(['new' => '500', 'free' => '200']);

        $this->actingAs($user)->post('/prescriptions', [
            'chamber_id' => $chamber->id, 'visit_type' => 'free',
            'patient' => ['name' => 'Rahim', 'age_years' => '30', 'gender' => 'male'], 'action' => 'draft',
        ])->assertSessionMissing('warning');

        $invoice = $this->invoiceOf(CaseHistory::firstOrFail());
        $this->assertSame('0.00', $invoice->total->toDecimal());
        $this->assertSame('Free visit', $invoice->note);
    }

    public function test_changing_the_visit_type_reprices_until_something_is_paid(): void
    {
        [$user, , $chamber] = $this->doctorWithFees();
        $case = $this->saveVisit($user, $chamber);
        $update = fn (string $type) => $this->actingAs($user)->put(route('prescriptions.update', $case), ['chamber_id' => $chamber->id, 'visit_type' => $type, 'action' => 'draft']);

        $update('follow_up');
        $this->assertSame('300.00', $this->invoiceOf($case)->total->toDecimal());
        $this->assertSame('Consultation · Follow-up', $this->invoiceOf($case)->lines[0]->description_en);

        $this->pay($this->invoiceOf($case), '100');
        $update('new');
        $this->assertSame('300.00', $this->invoiceOf($case)->total->toDecimal(), 'a paid bill keeps its price');
    }

    public function test_finalize_adds_billable_procedures_once(): void
    {
        [$user, , $chamber] = $this->doctorWithFees();
        $case = $this->saveVisit($user, $chamber, ['plan_items' => [
            ['description' => 'Scaling', 'fee' => '1500', 'is_billable' => '1'],
            ['description' => 'Polishing', 'fee' => '800.50', 'is_billable' => '1'],
            ['description' => 'Free check', 'fee' => '200', 'is_billable' => '0'],
            ['description' => 'No fee', 'is_billable' => '1'],
        ]]);
        $this->assertCount(1, $this->invoiceOf($case)->lines, 'drafts bill the consultation only');

        $this->post(route('prescriptions.finalize', $case))->assertSessionHasNoErrors();
        $invoice = $this->invoiceOf($case);

        $this->assertSame(['Consultation · New patient', 'Scaling', 'Polishing'], $invoice->lines->pluck('description_en')->all());
        $this->assertSame('2800.50', $invoice->total->toDecimal());
        $this->assertNotNull($invoice->lines[1]->treatment_plan_item_id);

        $this->post(route('prescriptions.finalize', $case)); // idempotent
        $this->assertCount(3, $this->invoiceOf($case)->lines);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.procedures_synced', 'auditable_id' => $invoice->id]);
    }

    public function test_procedures_added_after_full_payment_reopen_the_bill(): void
    {
        [$user, , $chamber] = $this->doctorWithFees();
        $case = $this->saveVisit($user, $chamber, ['plan_items' => [['description' => 'Scaling', 'fee' => '1000', 'is_billable' => '1']]]);
        $this->pay($this->invoiceOf($case), '500');
        $this->assertSame(InvoiceStatus::Paid, $this->invoiceOf($case)->status);

        $this->actingAs($user)->post(route('prescriptions.finalize', $case));

        $invoice = $this->invoiceOf($case);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertSame('1000.00', $invoice->due->toDecimal());
    }

    public function test_a_visit_saved_before_finance_gets_its_invoice_on_finalize(): void
    {
        [$user, $doctor, $chamber] = $this->doctorWithFees();
        $case = new CaseHistory;
        $case->patient_id = Patient::factory()->create()->id;
        $case->doctor_id = $doctor->id;
        $case->chamber_id = $chamber->id;
        $case->visit_no_today = 1;
        $case->visit_date = now()->startOfDay();
        $case->visit_type = 'new';
        $case->diagnosis = 'Fever';
        $case->save();
        $this->assertSame(0, Invoice::count());

        $this->actingAs($user)->post(route('prescriptions.finalize', $case))->assertSessionHasNoErrors();

        $this->assertSame('500.00', $this->invoiceOf($case)->total->toDecimal());
    }

    public function test_cancelling_a_draft_voids_its_unpaid_invoice(): void
    {
        [$user, , $chamber] = $this->doctorWithFees();
        $case = $this->saveVisit($user, $chamber);

        $this->post(route('prescriptions.cancel', $case), ['reason' => 'Patient left'])->assertSessionHasNoErrors();

        $invoice = $this->invoiceOf($case);
        $this->assertSame(InvoiceStatus::Voided, $invoice->status);
        $this->assertSame('Patient left', $invoice->void_reason);
        $this->assertSame('0.00', $invoice->due->toDecimal());
    }

    public function test_a_draft_with_a_payment_cannot_be_cancelled(): void
    {
        [$user, , $chamber] = $this->doctorWithFees();
        $case = $this->saveVisit($user, $chamber);
        $this->pay($this->invoiceOf($case), '200');

        $this->actingAs($user)->post(route('prescriptions.cancel', $case), ['reason' => 'Patient left'])->assertSessionHasErrors('status');

        $this->assertTrue($case->fresh()->isDraft());
        $this->assertSame(InvoiceStatus::PartiallyPaid, $this->invoiceOf($case)->status);
    }

    public function test_prescription_page_links_to_the_invoice_with_the_due(): void
    {
        [$user, , $chamber] = $this->doctorWithFees();
        $case = $this->saveVisit($user, $chamber);

        $this->get(route('prescriptions.show', $case))->assertOk()
            ->assertSee(route('invoices.show', $this->invoiceOf($case)), false)
            ->assertSee('৳500.00');
    }

    private function pay(Invoice $invoice, string $amount): Payment
    {
        $assistant = User::factory()->assistant()->create();
        $this->actingAs($assistant)->post(route('invoices.payments.store', $invoice), [
            'amount' => $amount, 'method' => 'cash', 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        return Payment::query()->latest('id')->firstOrFail();
    }
}
