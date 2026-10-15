<?php

namespace App\Http\Requests\Admin;

use App\Domain\Access\Role;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->targetUser();

        return $target !== null && (bool) $this->user()?->can('update', $target);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => Phone::normalizeLocal($this->input('phone'))]);
        }

        // Email is optional; a blank one is stored as NULL, never ''.
        if ($this->has('email') && blank($this->input('email'))) {
            $this->merge(['email' => null]);
        }
    }

    /**
     * Fields that are left out are not changed; a blank password keeps the
     * current one.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->targetUser())],
            'phone' => ['sometimes', 'required', 'string', Phone::LOCAL_RULE, Rule::unique('users', 'phone')->ignore($this->targetUser())],
            'password' => ['sometimes', 'nullable', 'string', Password::min(8)],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', Role::GUARD)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => __(Phone::LOCAL_MESSAGE),
            'phone.unique' => __('Another user already has this phone number.'),
            'email.unique' => __('Another user already has this email.'),
        ];
    }

    private function targetUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }
}
