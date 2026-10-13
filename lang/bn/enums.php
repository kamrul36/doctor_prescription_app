<?php

// Bangla enum labels (spec §6). To be checked by a native speaker before go-live.
// Bangla has no plural form for these units, so both forms are the same.

return [
    'dose_unit' => [
        'tablet' => 'ট্যাবলেট',
        'capsule' => 'ক্যাপসুল',
        'ml' => 'মি.লি.',
        'spoon' => 'চামচ',
        'drop' => 'ফোঁটা',
        'piece' => 'পিস',
        'puff' => 'পাফ',
        'sachet' => 'স্যাশে',
        'injection' => 'ইনজেকশন',
        'application' => 'বার লাগাবেন',
    ],
    'dose_timing' => [
        'before_meal' => 'খাবারের আগে',
        'after_meal' => 'খাবারের পরে',
        'any' => 'যেকোনো সময়',
    ],
    'duration_unit' => [
        'day' => 'দিন|দিন',
        'week' => 'সপ্তাহ|সপ্তাহ',
        'month' => 'মাস|মাস',
        'continuous' => 'চলবে',
        'as_needed' => 'প্রয়োজনে',
    ],
    'period_unit' => [
        'day' => 'দিন|দিন',
        'week' => 'সপ্তাহ|সপ্তাহ',
        'month' => 'মাস|মাস',
        'year' => 'বছর|বছর',
    ],
    'visit_status' => [
        'draft' => 'খসড়া',
        'finalized' => 'চূড়ান্ত',
        'cancelled' => 'বাতিল',
    ],
    'visit_type' => [
        'new' => 'নতুন রোগী',
        'follow_up' => 'ফলো-আপ',
        'report_show' => 'রিপোর্ট দেখানো',
        'free' => 'ফ্রি',
    ],
    'gender' => [
        'male' => 'পুরুষ',
        'female' => 'মহিলা',
        'other' => 'অন্যান্য',
    ],
    'patient_type' => [
        'general' => 'সাধারণ',
        'free' => 'ফ্রি',
        'staff' => 'স্টাফ',
    ],
    'print_mode' => [
        'pad_only' => 'শুধু প্যাড (ছাপানো প্যাডে)',
        'with_letterhead' => 'লেটারহেডসহ',
    ],
    'template_layout' => [
        'sidebar_left' => 'বাম পাশে সাইডবার',
        'single_column' => 'এক কলাম',
        'two_column' => 'দুই কলাম',
    ],
    'specialty_block' => [
        'gynae' => 'মাসিক ও প্রসূতি ইতিহাস',
        'dental' => 'দাঁতের পরীক্ষা',
    ],
    'print_locale' => [
        'en' => 'English',
        'bn' => 'বাংলা',
        'bilingual' => 'দ্বিভাষিক',
    ],
];
