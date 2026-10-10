<?php

namespace App\Http\Requests\Clinical;

use App\Domain\Clinical\DoseTiming;
use App\Domain\Clinical\DoseUnit;
use App\Domain\Clinical\DurationUnit;
use App\Domain\Clinical\Models\CaseHistory;
use App\Domain\Clinical\PeriodUnit;
use App\Domain\Clinical\Specialty\SpecialtySchemas;
use App\Domain\Clinical\TestKind;
use App\Domain\Clinical\ValueObjects\DosePattern;
use App\Domain\Clinical\ValueObjects\Duration;
use App\Domain\Clinical\Vitals;
use App\Domain\Patient\Gender;
use App\Domain\Practice\Models\Doctor;
use App\Domain\Practice\VisitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * The prescription pad (web form and `case-histories` API). Drafts may be
 * incomplete; a dose that is filled in must be a valid `M+N+E`, and a
 * duration must fit its unit. Finalizing checks completeness on top.
 */
class SaveVisitRequest extends FormRequest
{
    /** Row lists on the pad and the field that makes a row worth keeping. */
    private const ROWS = [
        'complaints' => 'text', 'findings' => 'value', 'plan_items' => 'description',
        'tests' => 'name', 'medicines' => 'name', 'advice' => 'text',
    ];

    public function authorize(): bool
    {
        /** @var CaseHistory|null $case */
        $case = $this->route('case');

        return (bool) ($case === null
            ? $this->user()?->can('create', CaseHistory::class)
            : $this->user()?->can('update', $case));
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        // Rows left empty on the pad are dropped.
        foreach (self::ROWS as $list => $field) {
            if (is_array($this->input($list))) {
                $merge[$list] = array_values(array_filter(
                    $this->input($list),
                    fn ($row) => is_array($row) && filled($row[$field] ?? null),
                ));
            }
        }

        // Phones are stored as digits (and a leading +), like on the patient form.
        $phone = $this->input('patient.phone');
        if (is_string($phone)) {
            $patient = (array) $this->input('patient');
            $patient['phone'] = preg_replace('/(?!^\+)[^\d]/', '', trim($phone)) ?: null;
            $merge['patient'] = $patient;
        }

        // Dose slots: "1/2", "0.5", "1.5" are stored as ½ / 1½.
        foreach ($merge['medicines'] ?? [] as $i => $row) {
            foreach (['dose_morning', 'dose_noon', 'dose_night'] as $slot) {
                if (isset($row[$slot]) && is_string($row[$slot]) && trim($row[$slot]) !== '') {
                    $merge['medicines'][$i][$slot] = DosePattern::normaliseSlot($row[$slot]);
                }
            }
        }

        $this->merge($merge);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var CaseHistory|null $case */
        $case = $this->route('case');
        $creating = $case === null;
        $period = Rule::enum(PeriodUnit::class);

        // Who: a registered patient, or details to register. Only when creating; the patient is fixed afterwards.
        $patientRules = $creating ? [
            'patient_id' => ['nullable', 'integer', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            'patient' => ['required_without:patient_id', 'nullable', 'array'],
            'patient.name' => ['required_without:patient_id', 'nullable', 'string', 'max:255'],
            'patient.age_years' => ['required_without:patient_id', 'nullable', 'integer', 'between:0,130'],
            'patient.gender' => ['required_without:patient_id', 'nullable', Rule::in(Gender::values())],
            'patient.phone' => ['required_without:patient_id', 'nullable', 'string', 'regex:/^\+?\d{6,15}$/'],
            'patient.address' => ['nullable', 'string', 'max:1000'],
            // Answer to "same person?": an existing patient's id, or "new".
            'patient_choice' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== 'new' && ! ctype_digit((string) $value)) {
                    $fail('Choose an existing patient or "register new".');
                }
            }],
        ] : [
            'patient_id' => ['prohibited'],
            'patient' => ['prohibited'],
        ];

        return [
            ...$patientRules,

            'chamber_id' => ['required', 'integer'],
            'visit_type' => ['nullable', Rule::enum(VisitType::class)],

            'complaints' => ['sometimes', 'array', 'max:30'],
            'complaints.*.text' => ['required', 'string', 'max:500'],
            'complaints.*.duration_value' => ['nullable', 'integer', 'between:1,999'],
            'complaints.*.duration_unit' => ['nullable', 'required_with:complaints.*.duration_value', $period],

            'findings' => ['sometimes', 'array', 'max:30'],
            'findings.*.label' => ['nullable', 'string', 'max:255'],
            'findings.*.value' => ['required', 'string', 'max:500'],

            ...Vitals::rules(),
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            ...SpecialtySchemas::rules($this->doctor()?->blocks() ?? []),

            'plan_items' => ['sometimes', 'array', 'max:30'],
            'plan_items.*.procedure_id' => ['nullable', 'integer', Rule::exists('procedures', 'id')],
            'plan_items.*.description' => ['required', 'string', 'max:500'],
            'plan_items.*.tooth_no' => ['nullable', 'string', 'max:32'],
            'plan_items.*.status' => ['nullable', Rule::in(['planned', 'done'])],
            'plan_items.*.fee' => ['nullable', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'plan_items.*.is_billable' => ['nullable', 'boolean'],

            'tests' => ['sometimes', 'array', 'max:50'],
            'tests.*.lab_test_id' => ['nullable', 'integer', Rule::exists('lab_tests', 'id')],
            'tests.*.name' => ['required', 'string', 'max:255'],
            'tests.*.timing_note' => ['nullable', 'string', 'max:100'],
            'tests.*.kind' => ['nullable', Rule::enum(TestKind::class)],
            'tests.*.result' => ['nullable', 'string', 'max:1000'],

            'medicines' => ['sometimes', 'array', 'max:30'],
            'medicines.*.name' => ['required', 'string', 'max:255'],
            'medicines.*.dose_morning' => ['nullable', 'string', 'max:8'],
            'medicines.*.dose_noon' => ['nullable', 'string', 'max:8'],
            'medicines.*.dose_night' => ['nullable', 'string', 'max:8'],
            'medicines.*.dose_unit' => ['nullable', Rule::enum(DoseUnit::class)],
            'medicines.*.timing' => ['nullable', Rule::enum(DoseTiming::class)],
            'medicines.*.duration_value' => ['nullable', 'integer', 'between:1,'.Duration::MAX_VALUE],
            'medicines.*.duration_unit' => ['nullable', Rule::enum(DurationUnit::class)],
            'medicines.*.instruction' => ['nullable', 'string', 'max:500'],

            'advice' => ['sometimes', 'array', 'max:20'],
            'advice.*.text' => ['required', 'string', 'max:5000'],
            'advice.*.text_bn' => ['nullable', 'string', 'max:5000'],
            'advice.*.source_template_id' => ['nullable', 'integer', Rule::exists('advice_templates', 'id')],

            'follow_up_value' => ['nullable', 'integer', 'between:1,365'],
            'follow_up_unit' => ['nullable', 'required_with:follow_up_value', $period],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes_private' => ['nullable', 'string', 'max:5000'],

            // Web form buttons: save the draft, or save and finalize.
            'action' => ['nullable', Rule::in(['draft', 'finalize'])],
        ];
    }

    /** A dose that is filled in must be complete and valid; a duration must fit its unit. */
    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ((array) $this->input('medicines', []) as $i => $row) {
                $slots = [$row['dose_morning'] ?? null, $row['dose_noon'] ?? null, $row['dose_night'] ?? null];
                $filled = array_filter($slots, fn ($s) => $s !== null && $s !== '');

                if ($filled !== []) {
                    try {
                        DosePattern::fromSlots(...$slots);
                    } catch (InvalidArgumentException $e) {
                        $validator->errors()->add("medicines.{$i}.dose", $e->getMessage());
                    }
                }

                if (filled($row['duration_unit'] ?? null)) {
                    try {
                        Duration::of($row['duration_value'] ?? null, $row['duration_unit']);
                    } catch (InvalidArgumentException $e) {
                        $validator->errors()->add("medicines.{$i}.duration_value", $e->getMessage());
                    }
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'patient.name' => 'patient name', 'patient.age_years' => 'age', 'patient.gender' => 'sex',
            'patient.phone' => 'phone', 'chamber_id' => 'chamber', 'medicines.*.name' => 'medicine name',
            'tests.*.name' => 'test name', 'complaints.*.text' => 'complaint',
        ];
    }

    private function doctor(): ?Doctor
    {
        return Doctor::query()->where('user_id', $this->user()?->id)->with('specialties')->first();
    }
}
