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
        // 驱动方式
        'type' => 'redis',
        // 服务器地址
        'host' => '127.0.0.1',
    ],
    'redis-239' => [
        // 驱动方式
        'type' => 'redis',
        // 服务器地址
        'host' => '192.168.1.239',
    ],
    // 文件缓存
    'file'      => [
        // 驱动方式
        'type' => 'file',
        // 设置不同的缓存保存目录
        'path' => '',
    ],


];
