<?php

return [
    'payment' => env('PAYMENT_SERVICE', 'robokassa'),
    'robokassa' => [
        'id' => env('ROBOKASSA_ID'),
        'is_test' => env('ROBOKASSA_TEST', 1),
        'login' => env('ROBOKASSA_LOGIN'),
        'pass1' => env('ROBOKASSA_PASS1'),
        'pass2' => env('ROBOKASSA_PASS2'),
    ],
    'order_id_extra' => env('ORDER_ID_EXTRA', ''),
];
