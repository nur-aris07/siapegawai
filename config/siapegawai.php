<?php

return [
    // Free registration stays closed until policy approval and the registration switch are valid. Official WhatsApp is optional.
    'registration_enabled' => (bool) env('SIAPEGAWAI_REGISTRATION_ENABLED', false),
    'google_auth_enabled' => (bool) env('SIAPEGAWAI_GOOGLE_AUTH_ENABLED', false),
    'account_email_enabled' => (bool) env('SIAPEGAWAI_ACCOUNT_EMAIL_ENABLED', false),
    'smtp_test_recipient' => env('SIAPEGAWAI_SMTP_TEST_RECIPIENT', ''),
    'whatsapp_number' => env('SIAPEGAWAI_WHATSAPP_NUMBER', ''),
    'privacy_version' => env('SIAPEGAWAI_PRIVACY_VERSION', 'DRAFT'),
    'privacy_approved' => (bool) env('SIAPEGAWAI_PRIVACY_APPROVED', false),
    'terms_approved' => (bool) env('SIAPEGAWAI_TERMS_APPROVED', false),
];
