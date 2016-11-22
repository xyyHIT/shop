<?php
use EasyWeChat\Foundation\Application;


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

    private $notice = [

    ];
//    const

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


    public static function sendNotice($openID,$template,$data){
        // x8zbClYOLTB8v-jSvKiwdeNfptBBDZ1BLW0FfVYYaB4
        self::init();
        $response = self::handler()->notice->send([
            'touser' => $_SESSION['wechat_user']['id'],
            'template_id' => 'rQcUAmp7X4MM2n7dzTwjIw1HjT0IRdfSnzlfvd6tbKQ',
            'url' => '',
            'topcolor' => '#f7f7f7',
            'data' => [
                "first"  => "恭喜你购买成功！",
                "name"   => "巧克力",
                "price"  => "39.8元",
                "remark" => "欢迎再次购买！",
            ],
        ]);
        return $response;
    }

}



/*接入微信端*/
//获取openid
//从拍卖user中查询，统一用户
//用户常登录操作
define('OPENID', '3'); //定义商城openid  与微信openid不同   满足用户常登录
//$user_mod = m('member');
//$info = $user_mod->get_info(OPENID);
//print_r($info);

