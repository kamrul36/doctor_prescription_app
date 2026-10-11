<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequiredMarkTest extends TestCase
{
    use RefreshDatabase;

    private const MARK = '<span class="text-red-600" aria-hidden="true">*</span>';

    public function test_required_labels_carry_a_red_asterisk_and_optional_ones_do_not(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Email '.self::MARK.'</label>', false)
            ->assertSee('Password '.self::MARK.'</label>', false);

        $this->actingAs(User::factory()->doctor()->create())->get('/patients/create')->assertOk()
            ->assertSee('Name '.self::MARK.'</label>', false)
            ->assertSee('Phone '.self::MARK.'</label>', false)
            ->assertSee('Alternate phone</label>', false)
            ->assertSee('Date of birth</label>', false);
    }
}
