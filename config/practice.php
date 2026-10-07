<?php

return [
    /*
    | Specialty codes a doctor or template can use. Each one may later get a
    | SpecialtySchema class (see the Phase 1 spec, section 4.4); until then the
    | code only selects which templates apply.
    */
    'specialties' => [
        'general' => 'General',
        'dental' => 'Dental',
        'gynae' => 'Gynaecology',
    ],
];
