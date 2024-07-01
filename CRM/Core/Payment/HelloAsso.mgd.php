<?php

return [
    [
        'name' => 'HelloAsso Payments Processor',
        'entity' => 'payment_processor_type',
        'params' => [
            'version' => 3,
            'title' => 'HelloAsso',
            'name' => 'HelloAsso',
            'description' => 'HelloAsso Payments Processor',
            'class_name' => 'Payment_HelloAsso',

            'user_name_label' => 'Client Id',
            'password_label' => 'Client Secret',
            'subject_label' => 'Organization Name',
            'url_site_default' => 'https://api.helloasso.com',
            'url_site_test_default' => 'https://api.helloasso-sandbox.com',
            'billing_mode' => 4,
            'payment_type' => 1,
        ],
    ],
];