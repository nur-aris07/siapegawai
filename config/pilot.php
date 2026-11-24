<?php
return [
    'qa_test_database' => env('SG_PILOT_QA_DB'),
    'mode' => false, // No production activation in this work item.
    'admission_enabled' => false,
    'starts_enabled' => false,
    'mail_enabled' => false,
    'edition_version_id' => null,
    'cohort_code' => null,
    'window_start' => null,
    'window_end' => null,
    'max_participants' => 8,
    'policy_version' => null,
    'research_version' => null,
    'privacy_sha256' => null,
    'terms_sha256' => null,
    'research_sha256' => null,
    'effective_at' => null,
];
