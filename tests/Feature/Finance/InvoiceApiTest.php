<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\Models\Invoice;
use App\Domain\Finance\Models\Payment;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\PracticeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/** The finance API (spec §10.2): same Actions and rules as the web, problem details on errors. */
class InvoiceApiTest extends TestCase
{
    use FinanceTestHelpers;
    use RefreshDatabase;

    private User $doctorUser;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AccessSeeder::class, PracticeSeeder::class]);
        [$this->doctorUser, , $chamber] = $this->doctorWithFees();
        $this->invoice = $this->invoiceOf($this->saveVisit($this->doctorUser, $chamber));
    }

    public function test_list_and_show_invoices(): void
    {
        $h = $this->bearer(User::factory()->assistant()->create());

        $this->getJson('/api/v1/invoices?unpaid=1', $h)->assertOk()
            ->assertJsonPath('data.0.invoice_no', $this->invoice->invoice_no)
            ->assertJsonPath('data.0.due', '500.00')
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/v1/invoices/{$this->invoice->id}", $h)->assertOk()
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.lines.0.kind', 'consultation')
            ->assertJsonPath('data.lines.0.unit_price', '500.00')
            ->assertJsonPath('data.payments', []);

        $this->getJson('/api/v1/invoices', $this->bearer(User::factory()->create()))->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_payment_needs_an_idempotency_key_and_replays_the_first_one(): void
    {
        $h = $this->bearer(User::factory()->assistant()->create());
        $url = "/api/v1/invoices/{$this->invoice->id}/payments";

        $this->postJson($url, ['amount' => '200', 'method' => 'cash'], $h)
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');

        $first = $this->postJson($url, ['amount' => '200', 'method' => 'cash'], $h + ['Idempotency-Key' => 'pay-0001-abc'])
            ->assertCreated()->assertJsonPath('data.amount', '200.00')->json('data');
        $this->postJson($url, ['amount' => '200', 'method' => 'cash'], $h + ['Idempotency-Key' => 'pay-0001-abc'])
            ->assertOk()->assertJsonPath('data.id', $first['id'])->assertJsonPath('data.receipt_no', $first['receipt_no']);
        $this->postJson($url, ['amount' => '250', 'method' => 'cash'], $h + ['Idempotency-Key' => 'pay-0001-abc'])
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');

        $this->assertSame(1, Payment::count());
        $this->postJson($url, ['amount' => '301', 'method' => 'cash'], $h + ['Idempotency-Key' => 'pay-0002-abc'])
            ->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->getJson("/api/v1/invoices/{$this->invoice->id}", $h)
            ->assertJsonPath('data.status', 'partially_paid')->assertJsonPath('data.due', '300.00')
            ->assertJsonCount(1, 'data.payments');
    }

    public function test_discount_line_price_void_and_receipt(): void
    {
        $h = $this->bearer($this->doctorUser);
        $id = $this->invoice->id;
        $lineId = $this->invoice->lines->first()->id;

        $this->putJson("/api/v1/invoices/{$id}/lines/{$lineId}", ['unit_price' => '600'], $h)->assertOk()->assertJsonPath('data.total', '600.00');
        $this->postJson("/api/v1/invoices/{$id}/discount", ['discount' => '100', 'discount_reason' => 'Staff family'], $h)
            ->assertOk()->assertJsonPath('data.total', '500.00')->assertJsonPath('data.discount', '100.00');
        $this->patchJson("/api/v1/invoices/{$id}", ['referred_by' => 'Dr. Karim'], $h)->assertOk()->assertJsonPath('data.referred_by', 'Dr. Karim');

        $paymentId = $this->postJson("/api/v1/invoices/{$id}/payments", ['amount' => '500', 'method' => 'nagad', 'reference' => 'NG1'], $h + ['Idempotency-Key' => 'pay-full-0001'])
            ->assertCreated()->json('data.id');
        $this->getJson("/api/v1/invoices/{$id}", $h)->assertJsonPath('data.status', 'paid');

        $this->get("/api/v1/payments/{$paymentId}/receipt?lang=en", $h)->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')->assertSee('PAID')->assertSee('Dr. Karim');

        $this->postJson("/api/v1/invoices/{$id}/void", ['reason' => 'Duplicate'], $h)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson("/api/v1/payments/{$paymentId}/void", ['reason' => 'Refund'], $h)->assertOk()->assertJsonPath('data.void_reason', 'Refund');
        $this->postJson("/api/v1/invoices/{$id}/void", ['reason' => 'Duplicate'], $h)->assertOk()->assertJsonPath('data.status', 'voided')->assertJsonPath('data.due', '0.00');
    }

    public function test_assistant_cannot_discount_or_void(): void
    {
        $h = $this->bearer(User::factory()->assistant()->create());
        $id = $this->invoice->id;

        $this->postJson("/api/v1/invoices/{$id}/discount", ['discount' => '50', 'discount_reason' => 'x x x'], $h)->assertForbidden();
        $this->postJson("/api/v1/invoices/{$id}/void", ['reason' => 'Duplicate'], $h)->assertForbidden();
        $this->putJson("/api/v1/invoices/{$id}/lines/{$this->invoice->lines->first()->id}", ['unit_price' => '1'], $h)->assertForbidden();
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        $this->app['auth']->forgetGuards();
        foreach (['tymon.jwt', 'tymon.jwt.auth', 'tymon.jwt.parser'] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
        JWTAuth::clearResolvedInstances();
        JWTAuth::unsetToken();

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
