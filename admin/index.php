<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);//满足版本升级 by xxy  20161026
/* 应用根目录 */
define('APP_ROOT', dirname(__FILE__));          //该常量只在后台使用
define('ROOT_PATH', dirname(APP_ROOT));   //该常量是ECCore要求的
define('IN_BACKEND', true);
include(ROOT_PATH . '/eccore/ecmall.php');

// composer 自动加载
include ROOT_PATH.'/vendor/autoload.php';

/* 定义配置信息 */
ecm_define(ROOT_PATH . '/data/config.inc.php');

// 配置时区
date_default_timezone_set('PRC');

/* 启动ECMall */
ECMall::startup(array(
    'default_app'   =>  'default',
    'default_act'   =>  'index',
    'app_root'      =>  APP_ROOT . '/app',
    'external_libs' =>  array(
        ROOT_PATH . '/includes/global.lib.php',
        ROOT_PATH . '/includes/libraries/time.lib.php',
        ROOT_PATH . '/includes/ecapp.base.php',
        ROOT_PATH . '/includes/plugin.base.php',
        APP_ROOT . '/app/backend.base.php',
        ROOT_PATH . '/includes/wx.base.php', // 微信类库
        ROOT_PATH . '/includes/Cache.php', // 缓存库
        ROOT_PATH . '/includes/Log.php', // 日志
    ),
));

?>