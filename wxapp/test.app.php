<?php

use \dubbo\dubboClient;

class TestApp extends MallbaseApp {

    public function test() {
        echo '这是个测试方法.';
    }

    function dubboCli() {
        $options= ["registry_address" => "192.168.1.239:25112"];
        $dubboCli = new dubboClient($options);

        echo '1111';exit;

        $HelloService = $dubboCli->getService("com.yijiawang.web.platform.messageCenter.service.NewsService","1.0.0",null);
        $ret = $HelloService->getNewsPage();

        echo $ret;
    }


    /**
     * 微信公众号开启服务
     */
    public function serve() {
        Wechat::handler()->server->serve()->send();
    }

    /**
     * 这里进行菜单的更新
     */
    public function updateMenu() {
        $buttons = [
            [
                "type" => "view",
                "name" => "图书",
                "url"  => "http://devtst.yijiapai.com/gavin?app=test&act=book"
            ],
            [
                "type" => "view",
                "name" => "电影",
                "url"  => "http://devtst.yijiapai.com/gavin?app=test&act=movie"
            ],
            [
                "type" => "view",
                "name" => "音乐",
                "url"  => "http://devtst.yijiapai.com/gavin?app=test&act=music"
            ]
        ];

        Wechat::handler()->menu->add($buttons);
    }

    public function oauthCallback() {
        $oauth = Wechat::handler()->oauth;

        // 获取 OAuth 授权结果用户信息
        $user = $oauth->user();
        $userArr = $user->toArray();
        $_SESSION['wechat_user'] = $userArr;

        $targetUrl = empty( $_SESSION['wechat_target_url'] ) ? '/' : $_SESSION['wechat_target_url'];

        // 跳转
        header('location:' . $targetUrl);
    }

    public function book(){
//echo json_encode($_SESSION['wechat_user']).'<br>';
//        echo '这里是图书专栏';

        //
        $notice = Wechat::handler()->notice;

        // $noticeArr = $notice->getPrivateTemplates();

        $tID = $notice->send([
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

        echo $tID;
    }

    public function movie(){
echo json_encode($_SESSION['wechat_user']).'<br>';

        echo '这里是电影专栏';
    }

    public function music(){
echo json_encode($_SESSION['wechat_user']).'<br>';

        echo '这里是音乐专栏';
    }


}