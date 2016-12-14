<?php
use EasyWeChat\Foundation\Application;
use EasyWeChat\Payment\Order;
use Doctrine\Common\Cache\RedisCache;

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

            // access_token 存储方式
            $cache = new RedisCache();

            $redis = Cache::store(WECHAT_USERINFO_REDIS)->handler();
            Cache::store('default'); // 使用完切换回default
            $cache->setRedis($redis);

            self::$handler->access_token->setCacheKey('YIJIAWANG_ACCESS_TOKEN_KEY');
            self::$handler->access_token->setCache($cache);
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
    public static function sendNotice($openID,$template,$data,$url=''){
        self::init();
        $response = self::handler()->notice->send([
            'touser' => $openID,
            'template_id' => $template,
            'url' => $url,
            'topcolor' => '#e86f0e',
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
        self::init();
		$payment = self::handler()->payment;
		$order = new Order($attributes);
		$result = $payment->prepare($order);
		if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS'){
			$prepayId = $result->prepay_id;
			$config = $payment->configForJSSDKPayment($prepayId);
			return $config;
		}
		return false;
	}
}
