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
        $this->call([AccessSeeder::class, PracticeSeeder::class, CatalogSeeder::class]);

        $adminEmail = config('access.seed_admin.email');
        $adminPassword = config('access.seed_admin.password');

        if ($adminEmail && $adminPassword) {
            $this->seedUser('Super Admin', $adminEmail, config('access.seed_admin.phone') ?: null, $adminPassword, Role::SUPER_ADMIN);
        }

        if (app()->environment('local')) {
            $this->seedUser('Demo Doctor', 'doctor@example.com', '01700000001', 'password', Role::DOCTOR);
            $this->seedUser('Demo Assistant', 'assistant@example.com', '01700000002', 'password', Role::ASSISTANT);
        }
    }

    /** An existing account keeps its values; only a missing phone is filled in. */
    private function seedUser(string $name, string $email, ?string $phone, string $password, string $role): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'phone' => $phone, 'password' => $password, 'is_active' => true],
        );

        if ($user->phone === null && $phone !== null && User::query()->where('phone', $phone)->doesntExist()) {
            $user->update(['phone' => $phone]);
        }

        $user->assignRole($role);
    }
}
