<?php

namespace App\Http\Requests\Clinical;

use App\Domain\Clinical\Models\CaseHistory;
use Illuminate\Foundation\Http\FormRequest;

class CancelVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CaseHistory $case */
        $case = $this->route('case');

        return (bool) $this->user()?->can('cancel', $case);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:500']];
    }
}
