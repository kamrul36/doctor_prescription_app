<?php

namespace Database\Seeders;

use App\Domain\Access\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([AccessSeeder::class, PracticeSeeder::class]);

        $adminEmail = config('access.seed_admin.email');
        $adminPassword = config('access.seed_admin.password');

        if ($adminEmail && $adminPassword) {
            $this->seedUser('Super Admin', $adminEmail, $adminPassword, Role::SUPER_ADMIN);
        }

        if (app()->environment('local')) {
            $this->seedUser('Demo Doctor', 'doctor@example.com', 'password', Role::DOCTOR);
            $this->seedUser('Demo Assistant', 'assistant@example.com', 'password', Role::ASSISTANT);
        }
    }

    private function seedUser(string $name, string $email, string $password, string $role): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password, 'is_active' => true],
        );

        $user->assignRole($role);
    }
}
