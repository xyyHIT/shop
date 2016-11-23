<?php
use EasyWeChat\Foundation\Application;

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

}
