<?php

namespace App\Http\Resources;

use App\Domain\Access\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    private bool $withPermissions = false;

    /** Adds the effective permission list (used by `auth/me`). */
    public function withPermissions(): static
    {
        $this->withPermissions = true;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            /** @var list<string> */
            'roles' => $this->getRoleNames()->values()->all(),
            /** @var list<string> */
            'permissions' => $this->when($this->withPermissions, fn () => $this->effectivePermissions()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return list<string> */
    private function effectivePermissions(): array
    {
        $names = $this->isSuperAdmin()
            ? Permission::values()
            : $this->getAllPermissions()->pluck('name')->all();

        sort($names);

        return $names;
    }
}
