<?php

namespace App\Http\Requests\Admin;

use App\Domain\Access\Role;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', User::class);
    }

    /** Unticked checkboxes send nothing, so a missing list means "none". */
    protected function prepareForValidation(): void
    {
        if (! $this->has('roles')) {
            $this->merge(['roles' => []]);
        }

        if ($this->has('phone')) {
            $this->merge(['phone' => Phone::normalizeLocal($this->input('phone'))]);
        }

        // Email is optional; a blank one is stored as NULL, never ''.
        if ($this->has('email') && blank($this->input('email'))) {
            $this->merge(['email' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', Phone::LOCAL_RULE, Rule::unique('users', 'phone')],
            'password' => ['required', 'string', Password::min(8)],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['array'],
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
}
