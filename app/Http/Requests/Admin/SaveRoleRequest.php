<?php

namespace App\Http\Requests\Admin;

use App\Domain\Access\Permission;
use App\Domain\Access\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role as RoleModel;

/** Create or update a role (the route has no {role} when creating). */
class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->targetRole();

        return (bool) ($role === null
            ? $this->user()?->can('create', RoleModel::class)
            : $this->user()?->can('update', $role));
    }

    /** Unticked checkboxes send nothing, so a missing list means "none". */
    protected function prepareForValidation(): void
    {
        if (! $this->has('permissions')) {
            $this->merge(['permissions' => []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')->where('guard_name', Role::GUARD)->ignore($this->targetRole()),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permission::values())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.regex' => 'Use lowercase letters, digits and underscores, starting with a letter.',
        ];
    }

    private function targetRole(): ?RoleModel
    {
        $role = $this->route('role');

        return $role instanceof RoleModel ? $role : null;
    }
}
