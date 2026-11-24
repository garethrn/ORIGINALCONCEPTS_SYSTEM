<?php

return [

    'company' => [
        'legal_name' => '',
        'trading_name' => 'Original Concepts',
        'address_line1' => '24 Henderson Road',
        'address_line2' => 'Athlone',
        'city' => 'Pietermaritzburg',
        'province' => '',
        'postal_code' => '3201',
        'country' => 'South Africa',
        'vat_number' => '',
        'registration_number' => '',
        'phone' => '+27 73 834 3781',
        'email' => 'info@originalconcepts.co.za',
        'website' => 'www.originalconcepts.co.za',
        'bbbee' => 'B-BBEE Level 1',
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
        'theme_color' => '#0b0f1a',
        'background_color' => '#0b0f1a',
    ],

];
