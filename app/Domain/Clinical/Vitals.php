<?php

namespace App\Domain\Clinical;

/**
 * The vitals block, stored as json on the visit. One definition for
 * validation, the pad and later printing.
 */
final class Vitals
{
    /** @var array<string, array{label: string, unit: string, rules: list<string>}> */
    public const FIELDS = [
        'bp_sys' => ['label' => 'BP systolic', 'unit' => 'mmHg', 'rules' => ['integer', 'between:40,300']],
        'bp_dia' => ['label' => 'BP diastolic', 'unit' => 'mmHg', 'rules' => ['integer', 'between:20,200']],
        'pulse' => ['label' => 'Pulse', 'unit' => '/min', 'rules' => ['integer', 'between:20,250']],
        'temp' => ['label' => 'Temp', 'unit' => '°F', 'rules' => ['numeric', 'between:90,110']],
        'weight_kg' => ['label' => 'Weight', 'unit' => 'kg', 'rules' => ['numeric', 'between:0.5,350']],
        'height_cm' => ['label' => 'Height', 'unit' => 'cm', 'rules' => ['numeric', 'between:20,250']],
        'spo2' => ['label' => 'SpO₂', 'unit' => '%', 'rules' => ['integer', 'between:50,100']],
    ];

    /** @return array<string, list<string>> */
    public static function rules(string $prefix = 'vitals'): array
    {
        $rules = [$prefix => ['nullable', 'array:'.implode(',', array_keys(self::FIELDS))]];

        foreach (self::FIELDS as $key => $field) {
            $rules["{$prefix}.{$key}"] = ['nullable', ...$field['rules']];
        }

        return $rules;
    }

    /**
     * Drops empty values; null when nothing was measured.
     *
     * @param  array<string, mixed>|null  $vitals
     * @return array<string, int|float>|null
     */
    public static function clean(?array $vitals): ?array
    {
        $clean = [];

        foreach (self::FIELDS as $key => $field) {
            $value = $vitals[$key] ?? null;
            if ($value !== null && $value !== '') {
                $clean[$key] = in_array('integer', $field['rules'], true) ? (int) $value : (float) $value;
            }
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * "BP 120/80 mmHg · Pulse 72/min".
     *
     * @param  array<string, int|float>|null  $vitals
     */
    public static function summary(?array $vitals): string
    {
        if (! $vitals) {
            return '';
        }

        $parts = [];
        if (isset($vitals['bp_sys'], $vitals['bp_dia'])) {
            $parts[] = "BP {$vitals['bp_sys']}/{$vitals['bp_dia']} mmHg";
        }
        foreach (self::FIELDS as $key => $field) {
            if (! str_starts_with($key, 'bp_') && isset($vitals[$key])) {
                $parts[] = "{$field['label']} {$vitals[$key]}{$field['unit']}";
            }
        }

        return implode(' · ', $parts);
    }
}
