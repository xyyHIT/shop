<?php

use \dubbo\dubboClient;
use Tencentyun\ImageV2;

class WechatApp extends MallbaseApp {

    /**
     * 万象优图上传文件
     */
    public function cloudImageUpload(){
        $uploadRet = ImageV2::upload($_FILES['file']['tmp_name'],CLOUD_IMAGE_BUCKET);

        echo json_encode($uploadRet);
    }


    /**
     * 微信上传文件
     */
    public function temporaryUpload(){
        $temporary = Wechat::handler()->material_temporary;
        $result = $temporary->uploadImage('/Users/Gavin/Downloads/s7e.jpg');
//        $result = $temporary->uploadImage($_FILES['file']['tmp_name']);

        echo json_encode($result);
    }

    /**
     * 微信下载多媒体文件
     */
    public function temporaryDownload(){
//        $mediaID = '3-PEDAxzGvui2HplCiQK5Cnd41UO48tCBaK_S9OqjK9qpduTAgJH6mmKCIwMcSnZ';
        $mediaID = 'qJlaSal7-c0BC4weBaZ5JIdQJ2D7-80WvFynrZI-UqJNMkJNDpEsF2YO6KUtzF_K';
        $temporary = Wechat::handler()->material_temporary;
        $result = $temporary->download($mediaID,'/tmp/');

//        var_dump();

        print_r($result);
    }



    public function test() {
        echo '这是个测试方法.';
    }

    /**
     * dubbo测试
     */
    function dubboCli() {
        $options= ["registry_address" => "192.168.1.239:25112"];
        $dubboCli = new dubboClient($options);

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
     * 更新公众帐号菜单
     */
    public function updateMenu() {
//        $buttons = [
//            [
//                "type" => "view",
//                "name" => "图书",
//                "url"  => "http://devtst.yijiapai.com/gavin?app=test&act=book"
//            ],
//            [
//                "type" => "view",
//                "name" => "电影",
//                "url"  => "http://devtst.yijiapai.com/gavin?app=test&act=movie"
//            ],
//            [
//                "type" => "view",
//                "name" => "音乐",
//                "url"  => "http://devtst.yijiapai.com/gavin?app=test&act=music"
//            ]
//        ];

        $buttons = [

        ];

        Wechat::handler()->menu->add($buttons);
    }

    /**
     * 获取公众帐号菜单
     */
    public function getMenu(){
        $menu = Wechat::handler()->menu;
        $arr = $menu->all();

//        {"menu":{"button":[{"type":"view","name":"艺加拍卖","url":"http:\/\/devtst.yijiapai.com\/yjpai\/platform\/wx\/init","sub_button":[]},
//            {"name":"艺加资讯","sub_button":[{"type":"view","name":"风雅","url":
//                "http:\/\/devtst.yijiapai.com\/yjpai\/news\/list.html?type=fengya","sub_button":[]},
//                {"type":"view","name":"艺家","url":"http:\/\/devtst.yijiapai.com\/yjpai\/news\/list.html?type=yijia","sub_button":[]},
//                {"type":"view","name":"聚焦","url":"http:\/\/devtst.yijiapai.com\/yjpai\/news\/list.html?type=jujiao","sub_button":[]}]},
//            {"name":"牵手艺加","sub_button":[{"type":"view","name":"关于我们","url":"http:\/\/devtst.yijiapai.com\/yjpai\/adBox\/info.html?
//            id=25","sub_button":[]},{"type":"view","name":"商务合作","url":"http:\/\/h5.eqxiu.com\/s\/Nrag1avO","sub_button":[]},
//                {"type":"view","name":"卖家帮助","url":"http:\/\/w.yijiapai.com\/yjpai\/adBox\/info.html?id=132","sub_button":[]},
//                {"type":"view","name":"买家帮助","url":"http:\/\/w.yijiapai.com\/yjpai\/adBox\/info.html?id=133","sub_button":[]},
//                {"type":"click","name":"联系客服","key":"contectKF","sub_button":[]}]}]}}

        echo json_encode($arr);
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


}