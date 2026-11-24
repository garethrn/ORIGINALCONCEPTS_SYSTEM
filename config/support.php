<?php

return [

    'company' => [
        'legal_name' => 'Original Concepts',
        'trading_name' => 'Original Concepts',
        'address_line1' => '',
        'address_line2' => '',
        'city' => '',
        'province' => '',
        'postal_code' => '',
        'country' => 'South Africa',
        'vat_number' => '',
        'registration_number' => '',
        'phone' => '',
        'email' => 'support@originalconcepts.co.za',
        'bank_name' => '',
        'bank_account' => '',
        'branch_code' => '',
        'invoice_footer' => 'Thank you for your business.',
        'default_labour_rate_cents' => '65000',
    ],

    'ai' => [
        'driver' => env('AI_DRIVER', 'openai'),
        'key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
        'enabled' => env('AI_DRIVER', 'openai') === 'fake' || filled(env('AI_API_KEY')),
    ],

    'pwa' => [
        'name' => 'Original Concepts Support',
        'short_name' => 'OC Support',
        'theme_color' => '#1a3c5e',
        'background_color' => '#1a3c5e',
    ],

];
