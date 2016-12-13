<?php
/*******************************************************************************
 * 艺加商城
 *
 * (c)  2016  Gavin(田宇)  <tianyu_0723@hotmail.com>
 *
 ******************************************************************************/

/**
 * 本地调试 配置文件
 *
 * Created by PhpStorm.
 * User: Gavin
 * Date: 2016/12/2
 * Time: 14:34
 */
return [
    'SITE_URL'           => 'http://127.0.0.1:8006',
    'DB_CONFIG'          => 'mysql://root:abc@192.168.1.23:3306/ejmall',

    'IS_WECHAT'           => false, // 是否在微信中运行
    'USER_ID'             => 64, // 如果不在微信运行  !!!自动登录,可调试接口!!!
    'WECHAT_USERINFO_URL' => 'http://devtst.yijiapai.com', // 跳转到拍卖获取微信信息的地址
    'WECHAT_USERINFO_REDIS' => 'redis-dev', // 从哪个redis获取微信用户信息


    /**
     * 微信公众号配置
     */
    'WECHAT_APP_ID' => 'wxf3dab84806d08208',// AppID
    'WECHAT_SECRET'  => '53ed6d3b2c08dc0eacf97ab1b6310ad8',// AppSecret
    'WECHAT_TOKEN' => 'wx_token',// Token
    'WECHAT_AES_KEY' => 'YOgcuOltH3z7rJqhsPDivKsnsTEFQRDJvGZEdu9QlLO',// EncodingAESKey，安全模式下请一定要填写！！！

];