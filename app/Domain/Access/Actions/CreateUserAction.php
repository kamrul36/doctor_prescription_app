<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\UserAccessRules;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateUserAction
{
    public function __construct(
        private readonly UserAccessRules $rules,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, password: string, is_active?: bool, roles?: list<string>}  $data
     */
    public function handle(User $actor, array $data): User
    {
        $roles = $data['roles'] ?? [];
        $this->rules->ensureCanAssign($actor, [], $roles);

        return DB::transaction(function () use ($data, $roles) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $user->syncRoles($roles);

            $this->audit->log('user.created', $user, ['roles' => $roles]);

            return $user;
        });
    }
}
