<?php

return [

    'accessKey' => '',
    'secretKey' => '',

    // 空间名为键，对应域名为值，第一个元素作为默认空间域名配置
    'bucket' => [
        // 'bucket1' => 'domain1',
        // 'bucket2' => 'domain2',
    ],

    // 使用持久化操作时，七牛会将执行通知发送到notify_url
    'notify_url' => '',

    // 是否使用laravel的缓存
    'use_laravel_cache' => 1,
];