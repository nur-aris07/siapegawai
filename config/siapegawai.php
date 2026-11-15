<?php

return [
    // Free registration stays closed until policy approval and the registration switch are valid. Official WhatsApp is optional.
    'registration_enabled' => (bool) env('SIAPEGAWAI_REGISTRATION_ENABLED', false),
    'whatsapp_number' => env('SIAPEGAWAI_WHATSAPP_NUMBER', ''),
    'privacy_version' => env('SIAPEGAWAI_PRIVACY_VERSION', 'DRAFT'),
    'privacy_approved' => (bool) env('SIAPEGAWAI_PRIVACY_APPROVED', false),
    'terms_approved' => (bool) env('SIAPEGAWAI_TERMS_APPROVED', false),
];
