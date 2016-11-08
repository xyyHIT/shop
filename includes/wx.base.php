<?php
/*接入微信端*/
//获取openid
//从拍卖user中查询，统一用户
//用户常登录操作
//define('OPENID','2'); //定义商城openid  与微信openid不同   满足用户常登录
$user_mod = m('member');

$info = $user_mod->get_info(OPENID);
//print_r($info);