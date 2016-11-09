<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);//满足版本升级 by xxy  20161026
define('ROOT_PATH', dirname(__FILE__));
include(ROOT_PATH . '/eccore/ecmall.php');

/* 定义配置信息 */
ecm_define(ROOT_PATH . '/data/config.inc.php');
$ECMall = new ECMall();

// 配置时区
date_default_timezone_set('PRC');

if(isset($_GET['api'])){
	$app_con = '/wxapp';
}else{
	$app_con = '/app';
}

/* 启动ECMall */
$ECMall->startup(array(
    'default_app'   =>  'default',
    'default_act'   =>  'index',
    'app_root'      =>  ROOT_PATH . $app_con,
    'external_libs' =>  array(
        ROOT_PATH . '/includes/global.lib.php',
        ROOT_PATH . '/includes/libraries/time.lib.php',
        ROOT_PATH . '/includes/ecapp.base.php',
        ROOT_PATH . '/includes/plugin.base.php',
        ROOT_PATH . '/app/frontend.base.php',
        ROOT_PATH . '/includes/subdomain.inc.php',
        ROOT_PATH . '/includes/wx.base.php'
    ),
));
?>
