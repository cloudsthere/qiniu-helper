<?php

return [

    'accessKey' => '0YBmkl_WFWlBrIEActy0ltskOgbc7kqRtgAofwO9',
    'secretKey' => 'qMsDI7ZCl_8vOT_sIjmnTKtsjO7IeCOqt_2HTOn4',

    // 空间名为键，对应域名为值，第一个元素作为默认空间域名配置
    'bucket' => [
        'test' => 'o9b6k34og.bkt.clouddn.com',
        'space' => 'o9is2ohd6.bkt.clouddn.com'
    ],

    // 使用持久化操作时，七牛会将执行通知发送到notify_url
    'notify_url' => 'http://apt.niowoo.com/notify',

    // 是否使用laravel的缓存
    'use_laravel_cache' => 1,
];