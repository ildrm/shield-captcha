<?php

return [
    'kavenegar' => [
        'enabled' => (bool) env('SHIELD_SMS_KAVENEGAR_ENABLED', false),
        'templates' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SHIELD_SMS_KAVENEGAR_TEMPLATES', '')),
        ))),
    ],
];
