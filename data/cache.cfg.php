<?php
/**
 * Created by PhpStorm.
 * User: Gavin
 * Date: 2016/11/12
 * Time: 14:50
 */
return [
    // 使用复合缓存类型
    'type'      => 'complex',
    // 默认使用的缓存
    'default'   => [
        'type' => 'redis',
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'select'     => 0,
        'timeout'    => 0,
        'expire'     => 0,
        'persistent' => false,
        'prefix'     => '',
    ],
    'redis-dev' => [
        'type' => 'redis',
        'host' => '192.168.1.239',
        'port'       => 6379,
        'password'   => 'manager',
        'select'     => 0,
        'timeout'    => 0, // 3600
        'expire'     => 0,
        'persistent' => false,
        'prefix'     => '',
    ],

    'redis-mall-test' => [
        'type' => 'redis',
        'host' => '10.141.11.136',
        'port'       => 6379,
        'password'   => 'manager',
        'select'     => 0,
        'timeout'    => 0, // 3600
        'expire'     => 0,
        'persistent' => false,
        'prefix'     => '',
    ],

    'redis-mall-product' => [
        'type' => 'redis',
        'host' => '10.0.0.194',
        'port'       => 6379,
        'password'   => 'crs-lodfe5ga:TFH2016yijia',
        'select'     => 0,
        'timeout'    => 3600,
        'expire'     => 0,
        'persistent' => false,
        'prefix'     => '',
    ],

    // 文件缓存
    'file'      => [
        // 驱动方式
        'type' => 'file',
        // 设置不同的缓存保存目录
        'path' => '',
    ],


];
