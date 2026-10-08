<?php

namespace Tests\Feature\Catalog;

use App\Domain\Catalog\Models\AdviceTemplate;
use App\Domain\Catalog\Models\LabTest;
use App\Domain\Catalog\Models\Procedure;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessSeeder::class);
    }

    public function test_guests_go_to_login(): void
    {
        $this->get('/catalog/lab-tests')->assertRedirect('/login');
    }

    public function test_doctor_sees_lists_and_manage_controls(): void
    {
        $this->seed(CatalogSeeder::class);
        $doctor = User::factory()->doctor()->create();

        $this->actingAs($doctor)->get('/catalog/lab-tests')->assertOk()->assertSee('CBC')->assertSee('New lab test');
        $this->actingAs($doctor)->get('/catalog/lab-tests?q=hba')->assertOk()->assertSee('HbA1c')->assertDontSee('CBC');
        $this->actingAs($doctor)->get('/catalog/procedures')->assertOk()->assertSee('SCALING')->assertSee('New procedure');
        $this->actingAs($doctor)->get('/catalog/advice-templates')->assertOk()->assertSee('After extraction');
        $this->actingAs($doctor)->get('/')->assertOk()->assertSee('Catalogs');
    }

    public function test_assistant_reads_but_sees_no_manage_controls_and_cannot_write(): void
    {
        $this->seed(CatalogSeeder::class);
        $assistant = User::factory()->assistant()->create();
        $test = LabTest::first();

        $this->actingAs($assistant)->get('/catalog/lab-tests')->assertOk()->assertSee('CBC')
            ->assertDontSee('New lab test')->assertDontSee('Show inactive')->assertDontSee('Delete');
        $this->actingAs($assistant)->get('/catalog/lab-tests/create')->assertForbidden();
        $this->actingAs($assistant)->post('/catalog/lab-tests', ['name' => 'X'])->assertForbidden();
        $this->actingAs($assistant)->get("/catalog/lab-tests/{$test->id}/edit")->assertForbidden();
        $this->actingAs($assistant)->put("/catalog/lab-tests/{$test->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($assistant)->delete("/catalog/lab-tests/{$test->id}")->assertForbidden();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get('/catalog/procedures')->assertForbidden();
    }

    public function test_lab_test_create_edit_delete_cycle(): void
    {
        $doctor = User::factory()->doctor()->create();

        $this->actingAs($doctor)->get('/catalog/lab-tests/create')->assertOk();
        $this->actingAs($doctor)->post('/catalog/lab-tests', ['name' => 'Widal', 'category' => 'Serology', 'is_active' => '1'])
            ->assertRedirect('/catalog/lab-tests')->assertSessionHas('status');
        $test = LabTest::where('name', 'Widal')->firstOrFail();

        $this->actingAs($doctor)->get("/catalog/lab-tests/{$test->id}/edit")->assertOk()->assertSee('Widal');
        $this->actingAs($doctor)->put("/catalog/lab-tests/{$test->id}", ['name' => 'Widal test', 'is_active' => '0'])
            ->assertRedirect('/catalog/lab-tests');
        $this->assertFalse($test->fresh()->is_active);

        // Inactive rows are listed only on request.
        $this->actingAs($doctor)->get('/catalog/lab-tests')->assertDontSee('Widal test');
        $this->actingAs($doctor)->get('/catalog/lab-tests?all=1')->assertSee('Widal test')->assertSee('Inactive');

        $this->actingAs($doctor)->delete("/catalog/lab-tests/{$test->id}")->assertRedirect('/catalog/lab-tests');
        $this->assertSoftDeleted($test);
        $this->actingAs($doctor)->get("/catalog/lab-tests/{$test->id}/edit")->assertNotFound();
    }

    public function test_invalid_input_goes_back_with_errors(): void
    {
        $doctor = User::factory()->doctor()->create();
        Procedure::create(['code' => 'RCT', 'name_en' => 'Root canal']);

        $this->actingAs($doctor)->from('/catalog/procedures/create')
            ->post('/catalog/procedures', ['code' => 'rct', 'name_en' => 'Dup', 'default_fee' => '12.345'])
            ->assertRedirect('/catalog/procedures/create')->assertSessionHasErrors(['code', 'default_fee']);
    }

    public function test_procedure_and_advice_forms_save(): void
    {
        $doctor = User::factory()->doctor()->create();

        $this->actingAs($doctor)->get('/catalog/procedures/create')->assertOk();
        $this->actingAs($doctor)->post('/catalog/procedures', [
            'code' => 'fill', 'name_en' => 'Filling', 'default_fee' => '800', 'is_billable' => '1', 'is_active' => '1',
        ])->assertRedirect('/catalog/procedures');
        $procedure = Procedure::where('code', 'FILL')->firstOrFail();
        $this->assertSame('800.00', $procedure->default_fee->toDecimal());
        $this->actingAs($doctor)->get("/catalog/procedures/{$procedure->id}/edit")->assertOk()->assertSee('800.00');

        $this->actingAs($doctor)->get('/catalog/advice-templates/create')->assertOk();
        $this->actingAs($doctor)->post('/catalog/advice-templates', [
            'specialty_code' => 'general', 'title' => 'Rest', 'text_en' => 'Take rest', 'is_default_for_template_id' => '', 'is_active' => '1',
        ])->assertRedirect('/catalog/advice-templates');
        $advice = AdviceTemplate::where('title', 'Rest')->firstOrFail();
        $this->actingAs($doctor)->get("/catalog/advice-templates/{$advice->id}/edit")->assertOk()->assertSee('Take rest');
    }
}
