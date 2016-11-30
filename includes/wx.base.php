<?php
use EasyWeChat\Foundation\Application;
use EasyWeChat\Payment\Order;

/**
 *
 * Class Wechat
 *
 * by Gavin 20161123
 */
class Wechat{

    /**
     * 操作句柄
     *
     * @var Application
     */
    private static $handler;

    /**
     * 配置信息
     *
     * @var array
     */
    private static $options;

    /**
     * 初始化
     *
     * @return Application
     */
    public static function init(){
        if(!self::$handler instanceof Application){
            self::$handler = new Application(self::options());
        }

        return self::$handler;
    }

    /**
     * 获取配置信息数组
     */
    public static function options(){
        $options = require ROOT_PATH.'/data/wechat.cfg.php';
        self::$options = $options;

        return self::$options;
    }

    /**
     * 获取操作句柄
     *
     * @return Application
     */
    public static function handler(){
        self::init();

        return self::$handler;
    }

	/*edit by newrain*/
    public static function sendNotice($openID,$template,$data){
        self::init();
        $response = self::handler()->notice->send([
            'touser' => $openID,
            'template_id' => $template,
            'url' => '',
            'topcolor' => '#f7f7f7',
			'data' => $data,
        ]);
        return $response;
    }

    /**
     * 获取用户信息
     * 如果获取不到,需要用户去关注
     *
     * @param $openid
     *
     * @return mixed
     */
    public static function getUserInfo($openid){
        $userModel =& m('member');

        $userArr = current($userModel->find([
            'conditions' => 'openid=\''.$openid.'\'',
        ]));

        if($userArr !== false){
            // 如果数据库已存在就拿出
            return $userArr;
        }else{
            // 不存在就调用接口获取数据

        }

    }
	
	/**
     * 获取用户信息
     * 如果获取不到,需要用户去关注
     *
     * @param $openid
     *
     * @return mixed
    */
	public static function pay($attributes){
		$options =	[
			/**
			 * Debug 模式，bool 值：true/false
			 *
			 * 当值为 false 时，所有的日志都不会记录
			 */
			'debug'   => false,
			// 艺闻美学
			'app_id'  => 'wx0e0bc55effb822b4', // AppID
			'secret'  => '8a2bc1ce662728adf6b4b4c941174187 ', // AppSecret
			'token'   => 'wx_token',  // Token
			'aes_key' => 'Mb3ddasQdg1hAGbrynWOFaiPBQ8XGPKQK4pEfr4bcmm', // EncodingAESKey，安全模式下请一定要填写！！

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
				//'scopes'   => [ 'snsapi_userinfo' ], // 有弹框确认,用户信息都可取到
				'scopes'   => [ 'snsapi_base' ], // 没有弹框,只能拿到openid
				//'callback' => '/gavin/?app=test&act=oauthCallback', // 沙盒帐号测试没问题
				'callback' => '/?app=wechat&act=oauthCallback',
			],
			/**
			 * 微信支付
			 */
			'payment' => [
				'merchant_id' => '1343527201',
				'key'         => 'H7zVQwLMe2JwqHs8U7zCmcmtbP24cNc4',
				'cert_path'   => 'path/to/your/cert.pem', // XXX: 绝对路径！！！！
				'key_path'    => 'path/to/your/key',      // XXX: 绝对路径！！！！
			],
			/**
			 * Guzzle 全局设置
			 *
			 * 更多请参考： http://docs.guzzlephp.org/en/latest/request-options.html
			 */
			'guzzle'  => [
				'timeout' => 3.0, // 超时时间（秒）
				//'verify' => false, // 关掉 SSL 认证（强烈不建议！！！）
			],
		];
		$app = new Application($options);
		$payment = $app->payment;
		/*
		$attributes = [
			'trade_type'       => 'JSAPI', // JSAPI，NATIVE，APP...
			'body'             => 'iPad mini 16G 白色',
			'detail'           => 'iPad mini 16G 白色',
			'out_trade_no'     => '1217752501201407033233368018',
			'total_fee'        => 5388,
			'notify_url'       => '', // 支付结果通知网址，如果不设置则会使用配置里的默认地址
			'openid'           => , // trade_type=JSAPI，此参数必传，用户在商户appid下的唯一标识，
		];*/
		$order = new Order($attributes);
		$result = $payment->prepare($order);
		if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS'){
			$prepayId = $result->prepay_id;
			return $prepayId;
		}
		return false;
	}
}
