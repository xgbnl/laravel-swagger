<?php

return [
    'info'          => [
        'title'       => config('app.name'),
        'description' => '接口文档 v1.0',
        'version'     => '1.0.0',
    ],
    'servers'       => [
        [
            'url'         => config('app.url') . '/api',
            'description' => '开发环境',
        ],
    ],
    'api_key'       => [
        'middlewares' => ['auth:api'],
        'definition'  => [
            'type' => 'apiKey',
            'name' => 'X-Token',
            'in'   => 'header',
        ],
    ],
    'restful'       => false,
    'base_response' => [
        'data_key' => 'data',
        'schema'   => [
            'code' => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => '错误码',
            ],
            'msg'  => [
                'type'        => 'string',
                'description' => '错误信息',
            ],
        ],
    ],
    'excluded_uris' => [],
];
