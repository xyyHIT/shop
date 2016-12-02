<?php
/**
 * Created by PhpStorm.
 * User: Gavin
 * Date: 2016/11/17
 * Time: 11:43
 */
// http://devtst.yijiapai.com/yjpai/server/wx/check
return [
    /**
     * Debug 模式，bool 值：true/false
     *
     * 当值为 false 时，所有的日志都不会记录
     */
    'debug'   => false,
    /**
     * 账号基本信息，请从微信公众平台/开放平台获取
     */

    // 艺美易拍
    'app_id'  => 'wxf3dab84806d08208', // AppID
    'secret'  => '53ed6d3b2c08dc0eacf97ab1b6310ad8', // AppSecret
    'token'   => 'wx_token',  // Token
    'aes_key' => 'YOgcuOltH3z7rJqhsPDivKsnsTEFQRDJvGZEdu9QlLO', // EncodingAESKey，安全模式下请一定要填写！！！

    // 公众号沙盒帐号测试
//    'app_id'  => 'wx49616c3197a5e9de',
//    'secret'  => 'dc43865a83f0a3036d0f6a6a31606201',
//    'token'   => 'wx_token',
//    'aes_key' => '',

    /**
     * 日志配置
     *
     * level: 日志级别, 可选为：
     *         debug/info/notice/warning/error/critical/alert/emergency
     * file：日志文件位置(绝对路径!!!)，要求可写权限
     */
    'log'     => [
        'level' => 'debug',
        'file'  => ROOT_PATH . '/temp/wechat.log',
    ],
    /**
     * OAuth 配置
     *
     * scopes：公众平台（snsapi_userinfo / snsapi_base），开放平台：snsapi_login
     * callback：OAuth授权完成后的回调页地址
     */
    'oauth'   => [
//        'scopes'   => [ 'snsapi_userinfo' ], // 有弹框确认,用户信息都可取到
        'scopes'   => [ 'snsapi_base' ], // 没有弹框,只能拿到openid
//        'callback' => '/gavin/?app=test&act=oauthCallback', // 沙盒帐号测试没问题
        'callback' => '/?app=wechat&act=oauthCallback',
    ],
    /**
     * 微信支付
     */
    'payment' => [
        'merchant_id' => 'your-mch-id',
        'key'         => 'key-for-signature',
        'cert_path'   => 'path/to/your/cert.pem', // XXX: 绝对路径！！！！
        'key_path'    => 'path/to/your/key',      // XXX: 绝对路径！！！！
        // 'device_info'     => '013467007045764',
        // 'sub_app_id'      => '',
        // 'sub_merchant_id' => '',
        // ...
    ],
    /**
     * Guzzle 全局设置
     *
     * 更多请参考： http://docs.guzzlephp.org/en/latest/request-options.html
     */
    'guzzle'  => [
        'timeout' => 10.0, // 超时时间（秒）
        //'verify' => false, // 关掉 SSL 认证（强烈不建议！！！）
    ],
];