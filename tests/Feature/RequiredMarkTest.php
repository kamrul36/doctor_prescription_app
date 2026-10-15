<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequiredMarkTest extends TestCase
{
    use RefreshDatabase;

    private const MARK = '<span class="text-red-600" aria-hidden="true">*</span>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessSeeder::class);
    }

    public function test_required_labels_carry_a_red_asterisk_and_optional_ones_do_not(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Email or phone '.self::MARK.'</label>', false)
            ->assertSee('Password '.self::MARK.'</label>', false);

        $this->actingAs(User::factory()->doctor()->create())->get('/patients/create')->assertOk()
            ->assertSee('Name '.self::MARK.'</label>', false)
            ->assertSee('Phone '.self::MARK.'</label>', false)
            ->assertSee('Alternate phone</label>', false)
            ->assertSee('Address</label>', false)
            ->assertSee('Date of birth</label>', false);
    }
}
