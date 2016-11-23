<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);//满足版本升级 by xxy  20161026
header("Access-Control-Allow-Origin: *");
define('ROOT_PATH', dirname(__FILE__));
include(ROOT_PATH . '/eccore/ecmall.php');

// composer 自动加载
include ROOT_PATH.'/vendor/autoload.php';

/* 定义配置信息 */
ecm_define(ROOT_PATH . '/data/config.inc.php');
$ECMall = new ECMall();

// 配置时区
date_default_timezone_set('PRC');
// 配置错误码
define('ERROR_CODE',json_encode(require ROOT_PATH.'/data/errcode.cfg.php'));

// 万象优图空间名称
define('CLOUD_IMAGE_BUCKET','shoptest'); // 测试环境
// define('CLOUD_IMAGE_BUCKET','shoppe'); // 正式环境

// 是否在微信中运行
define('IS_WECHAT',false);
// 如果不在微信运行 需要自动登录,可调试接口
define('USER_ID', '3');

/* 启动ECMall */
$ECMall->startup(array(
    'default_app'   =>  'default',
    'default_act'   =>  'index',
    'app_root'      =>  ROOT_PATH . '/wxapp',
    'external_libs' =>  array(
        ROOT_PATH . '/includes/global.lib.php',
        ROOT_PATH . '/includes/libraries/time.lib.php',
        ROOT_PATH . '/includes/ecapp.base.php',
        ROOT_PATH . '/includes/plugin.base.php',
        ROOT_PATH . '/wxapp/frontend.base.php',
        ROOT_PATH . '/includes/subdomain.inc.php',
        ROOT_PATH . '/includes/wx.base.php', // 微信类库
        ROOT_PATH . '/includes/Cache.php', // 缓存库
    ),
));
?>
