<?php

// Enum labels (spec §6). Keys: snake-cased enum name => case value. Bangla twin: lang/bn/enums.php.
// `a|b` entries are plural forms for trans_choice (1 => a, otherwise b).

return [
    'dose_unit' => [
        'tablet' => 'Tablet',
        'capsule' => 'Capsule',
        'ml' => 'ml',
        'spoon' => 'Spoon',
        'drop' => 'Drop',
        'piece' => 'Piece',
        'puff' => 'Puff',
        'sachet' => 'Sachet',
        'injection' => 'Injection',
        'application' => 'Application',
    ],
    'dose_timing' => [
        'before_meal' => 'Before meal',
        'after_meal' => 'After meal',
        'any' => 'Any time',
    ],
    'duration_unit' => [
        'day' => 'day|days',
        'week' => 'week|weeks',
        'month' => 'month|months',
        'continuous' => 'continue',
        'as_needed' => 'as needed',
    ],
    'period_unit' => [
        'day' => 'day|days',
        'week' => 'week|weeks',
        'month' => 'month|months',
        'year' => 'year|years',
    ],
    'visit_status' => [
        'draft' => 'Draft',
        'finalized' => 'Finalized',
        'cancelled' => 'Cancelled',
    ],
    'visit_type' => [
        'new' => 'New patient',
        'follow_up' => 'Follow-up',
        'report_show' => 'Report show',
        'free' => 'Free',
    ],
    'gender' => [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
    ],
    'patient_type' => [
        'general' => 'General',
        'free' => 'Free',
        'staff' => 'Staff',
    ],
    'print_mode' => [
        'pad_only' => 'Pad only (pre-printed stationery)',
        'with_letterhead' => 'With letterhead',
    ],
    'template_layout' => [
        'sidebar_left' => 'Left sidebar',
        'single_column' => 'Single column',
        'two_column' => 'Two columns',
    ],
    'print_locale' => [
        'en' => 'English',
        'bn' => 'বাংলা',
        'bilingual' => 'Bilingual',
    ],
];
