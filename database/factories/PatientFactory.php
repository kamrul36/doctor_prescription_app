<?php

namespace Database\Factories;

use App\Domain\Patient\Gender;
use App\Domain\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Patient> */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            // Real patients get theirs from NumberGenerator; the factory must not touch the counter.
            'code' => fake()->unique()->numerify('99########'),
            'name' => fake()->name(),
            'dob' => fake()->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::values()),
            'phone' => fake()->unique()->numerify('017########'),
            'address' => fake()->address(),
            'patient_type' => 'general',
        ];
    }
}
