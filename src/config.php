<?php

return [

    'accessKey' => 'your_accessKey',
    'secretKey' => 'your_secretKey',

    // 空间名为键，对应域名为值，第一个元素作为默认空间域名配置
    'bucket' => [
        'your_bucket' => 'domain of this bucket',
        'another_bucket' => 'domain of this bucket',
    ],


    'notify_url' => 'http://notify',

    // 是否使用laravel的缓存
    'use_laravel_cache' => 1,
];