<?php

// +----------------------------------------------------------------------
// | 缓存设置
// +----------------------------------------------------------------------

return [
    // 默认缓存驱动（支持从环境变量或数据库配置读取）
    // 优先级：环境变量 > 数据库配置 > 默认file
    'default' => env('cache.driver', 'file'),

    // 缓存连接方式配置
    'stores'  => [
        'file' => [
            // 驱动方式
            'type'       => 'File',
            // 缓存保存目录
            'path'       => '',
            // 缓存前缀
            'prefix'     => '',
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 序列化机制 例如 ['serialize', 'unserialize']
            'serialize'  => [],
        ],
        // Redis缓存
        'redis' => [
            // 驱动方式
            'type'       => 'redis',
            // 服务器地址
            'host'       => env('redis.host', '127.0.0.1'),
            // 端口
            'port'       => env('redis.port', 6379),
            // 密码
            'password'   => env('redis.password', ''),
            // 缓存前缀
            'prefix'     => 'xinyue_',
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 数据库索引
            'select'     => env('redis.select', 0),
            // 超时时间
            'timeout'    => 0,
            // 是否持久化
            'persistent' => false,
            // 序列化机制
            'serialize'  => ['serialize', 'unserialize'],
        ],
        'memcached' => [
            'type' => 'memcached',
            'host' => env('memcached.host', '127.0.0.1'),
            'port' => env('memcached.port', 11211),
            'username' => env('memcached.username', ''),
            'password' => env('memcached.password', ''),
            'expire' => 0,
            'prefix' => 'xinyue_',
            'timeout' => 2,
        ],
        // 更多的缓存连接
    ],
];
