<?php
/*******************************************************************************
 * 艺加商城
 *
 * (c)  2016  Gavin(田宇)  <tianyu_0723@hotmail.com>
 *
 ******************************************************************************/

/**
 * 艺文美学 配置文件
 *
 * Created by PhpStorm.
 * User: Gavin
 * Date: 2016/12/2
 * Time: 14:34
 */
return [
    'SITE_URL'           => 'http://tst.yijiapai.com',
    'DB_CONFIG'          => 'mysql://yjdb_test:taifenghui2016@10.66.145.146:3306/yijiamall_test',


    'IS_WECHAT'           => true, // 是否在微信中运行
//    'USER_ID'             => 3, // 如果不在微信运行  !!!自动登录,可调试接口!!!
    'WECHAT_USERINFO_URL' => 'http://tst.yijiapai.com', // 跳转到拍卖获取微信信息的地址
    'WECHAT_USERINFO_REDIS' => 'redis-mall-test', // 从哪个redis获取微信用户信息


    /**
     * 微信公众号配置
     */
    'WECHAT_APP_ID' => 'wx0e0bc55effb822b4',// AppID
    'WECHAT_SECRET'  => '8a2bc1ce662728adf6b4b4c941174187',// AppSecret
    'WECHAT_TOKEN' => 'wx_token',// Token
    'WECHAT_AES_KEY' => 'Mb3ddasQdg1hAGbrynWOFaiPBQ8XGPKQK4pEfr4bcmm',// EncodingAESKey，安全模式下请一定要填写！！！

];