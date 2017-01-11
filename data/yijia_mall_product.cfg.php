<?php
/*******************************************************************************
 * 艺加商城
 *
 * (c)  2016  Gavin(田宇)  <tianyu_0723@hotmail.com>
 *
 ******************************************************************************/

/**
 * 艺加商城 生产环境 配置文件
 *
 * Created by PhpStorm.
 * User: Gavin
 * Date: 2017/1/11
 * Time: 17:34
 */
return [
    'SITE_URL'           => 'http://w.yijiapai.com',
    'DB_CONFIG'          => 'mysql://shop_yj:mall#TFHyj@10.0.0.161:3306/yijiamall',


    'IS_WECHAT'           => true, // 是否在微信中运行
    'WECHAT_USERINFO_URL' => 'http://w.yijiapai.com', // 跳转到拍卖获取微信信息的地址
    'WECHAT_USERINFO_REDIS' => 'redis-mall-product', // 从哪个redis获取微信用户信息


    /**
     * 微信公众号配置
     */
    'WECHAT_APP_ID' => 'wxb3df2d16194b7da6',// AppID
    'WECHAT_SECRET'  => 'e4f3d2e24d2376f6a9b330ef47a3d848',// AppSecret
    'WECHAT_TOKEN' => 'wx_token',// Token
    'WECHAT_AES_KEY' => 'n7YFGrqkCFf00qFIMfEaebhYmWCwgmdv6ZF86hYycsS',// EncodingAESKey，安全模式下请一定要填写！！！

];