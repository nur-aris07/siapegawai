<?php

return [
    // Keep registration closed until approved privacy/terms and official WA are supplied.
    'registration_enabled' => (bool) env('SIAPEGAWAI_REGISTRATION_ENABLED', false),
    'whatsapp_number' => env('SIAPEGAWAI_WHATSAPP_NUMBER', ''),
    'privacy_version' => env('SIAPEGAWAI_PRIVACY_VERSION', 'DRAFT'),
    'privacy_approved' => (bool) env('SIAPEGAWAI_PRIVACY_APPROVED', false),
    'terms_approved' => (bool) env('SIAPEGAWAI_TERMS_APPROVED', false),
];
