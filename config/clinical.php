<?php

return [
    /*
    | A new visit is suggested as a follow-up when the patient's last finalized
    | visit with the same doctor is at most this many days old (spec §7.2).
    */
    'follow_up_window_days' => (int) env('CLINICAL_FOLLOW_UP_WINDOW_DAYS', 14),

    /*
    | Possible-duplicate check when a prescription registers a new patient:
    | same phone, or a name at least this similar (0..1) with an age within
    | `duplicate_age_tolerance` years.
    */
    'duplicate_name_similarity' => 0.85,
    'duplicate_age_tolerance' => 2,
];
