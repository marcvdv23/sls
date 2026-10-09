<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform / tenant identity
    |--------------------------------------------------------------------------
    |
    | SLS is the generic Sales platform. Each deployment can represent a
    | different operating entity and business workspace without forking the
    | codebase. Keep deployment-specific identity in .env/config, and keep
    | CRM, crawler, and intelligence behavior configurable in the application.
    |
    */
    'platform' => [
        'name' => env('SLS_PLATFORM_NAME', 'SLS'),
        'full_name' => env('SLS_PLATFORM_FULL_NAME', 'Sales'),
        'legacy_name' => env('SLS_LEGACY_NAME', '1G-SLS'),
    ],

    'entity' => [
        'key' => env('SLS_ENTITY_KEY', '2interact'),
        'name' => env('SLS_ENTITY_NAME', '2interact'),
        'display_name' => env('SLS_ENTITY_DISPLAY_NAME', env('SLS_ENTITY_NAME', '2interact')),
    ],

    'workspace' => [
        'key' => env('SLS_WORKSPACE_KEY', 'social_security'),
        'name' => env('SLS_WORKSPACE_NAME', '2Interact'),
        'description' => env('SLS_WORKSPACE_DESCRIPTION', '2Interact sales intelligence across HRMS, SSAS, EBPC, and ERMS product lines, including social security, pensions, benefits, HR/payroll, risk, compliance, budgeting, and related tenders.'),
        'domain_label' => env('SLS_WORKSPACE_DOMAIN_LABEL', '2Interact Public Sector Software'),
        'opportunity_label' => env('SLS_WORKSPACE_OPPORTUNITY_LABEL', 'Curated 2Interact opportunities'),
        'review_label' => env('SLS_WORKSPACE_REVIEW_LABEL', 'Review Desk'),
    ],

    'products' => [
        'default_code' => env('SLS_DEFAULT_PRODUCT_CODE', 'SSAS'),
        'default_name' => env('SLS_DEFAULT_PRODUCT_NAME', 'Interact SSAS'),
        'order' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('SLS_PRODUCT_ORDER', 'SSAS,HRMS,ERMS,EBPC'))
        ))),
    ],

    'review_mapping_selector' => env('SLS_REVIEW_MAPPING_SELECTOR', 'multi_select_dropdown'),
];
