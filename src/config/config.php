<?php

return [
    'endpoint' => env('OSS_ENDPOINT', 'oss-cn-qingdao.aliyuncs.com'),

    'endpoint_internal' => env('OSS_ENDPOINT_INTERNAL', 'oss-cn-qingdao-internal.aliyuncs.com'),

    'internal_upload' => env('OSS_USE_INTERNAL', false),

    'access_key_id' => env('OSS_ACCESS_KEY_ID', ''),

    'access_key_secret' => env('OSS_ACCESS_KEY_SECRET', ''),

    'bucket' => env('OSS_BUCKET', ''),

    'callback_url' => env('OSS_CALLBACK_URL', ''),

    'isinternal' => false
];