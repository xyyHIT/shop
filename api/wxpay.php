<?php
//完成微信支付回调
/* 应用根目录 */
define('APP_ROOT', dirname(__FILE__));
define('ROOT_PATH', dirname(APP_ROOT));
include(APP_ROOT . '/ecmall.php');
// composer 自动加载
include ROOT_PATH.'/vendor/autoload.php';

/* 定义配置信息 */
ecm_define(ROOT_PATH . '/data/config.inc.php');

/* 启动ECMall */
ECMall::startup(array(
    'default_app'   =>  'uc',
    'default_act'   =>  'index',
    'app_root'      =>  APP_ROOT,
    'external_libs' =>  array(
        ROOT_PATH . '/includes/global.lib.php',
        ROOT_PATH . '/includes/libraries/time.lib.php',
        ROOT_PATH . '/includes/ecapp.base.php',
        ROOT_PATH . '/includes/plugin.base.php',
		ROOT_PATH . '/includes/wx.base.php', // 微信类库
        APP_ROOT  . '/api.base.php',
    ),
));

?>