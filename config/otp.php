<?php

return [
    'expiry_duration' => env('OTP_EXPIRY_DURATION'),
    'retries' => env('OTP_RETRIES'),
    'retry_duration' => env('OTP_RETRY_DURATION'),
    'delivery_method' => env('OTP_DELIVERY_METHOD', 'mail'),
    // 'dev_emails' => [
    //     'erdumadnan@gmail.com',
    //     'saad0416@gmail.com',
    //     'oasamahussain@gmail.com',
    //     'simrashafqat14@gmail.com',
    //     'mohedaziz123@gmail.com',
    //     'fahad.didx@gmail.com',
    //     'mahadasif89@gmail.com',
    // ],
];
