<?php

use \dubbo\dubboClient;
use Tencentyun\ImageV2;

class WechatApp extends MallbaseApp
{

    

    /**
     * 拍卖修改用户信息
     */
    public function notify(){
        echo '哈哈哈哈哈';
    }

    /**
     * 微信回调,获取用户openid
     */
    public function oauthCallback()
    {
        // 获取 OAuth 授权结果用户信息
        $openid = Wechat::handler()->oauth->user()->toArray()['id'];
        $_SESSION['wx_openid'] = $openid;

        $targetUrl = empty( $_SESSION['wx_target_url'] ) ? '/' : $_SESSION['wx_target_url'];

        // 跳转
        header('location:' . $targetUrl);
    }

    /**
     * 万象优图上传文件
     */
    public function cloudImageUpload()
    {
        $uploadRet = ImageV2::upload($_FILES['file']['tmp_name'], CLOUD_IMAGE_BUCKET);

        echo json_encode($uploadRet);
    }


    /**
     * 微信上传文件
     */
    public function temporaryUpload()
    {
        $temporary = Wechat::handler()->material_temporary;
        $result = $temporary->uploadImage('/Users/Gavin/Downloads/s7e.jpg');
//        $result = $temporary->uploadImage($_FILES['file']['tmp_name']);

        echo json_encode($result);
    }

    /**
     * 微信下载多媒体文件
     */
    public function temporaryDownload()
    {
//        $mediaID = '3-PEDAxzGvui2HplCiQK5Cnd41UO48tCBaK_S9OqjK9qpduTAgJH6mmKCIwMcSnZ';
        $mediaID = 'qJlaSal7-c0BC4weBaZ5JIdQJ2D7-80WvFynrZI-UqJNMkJNDpEsF2YO6KUtzF_K';
        $temporary = Wechat::handler()->material_temporary;
        $result = $temporary->download($mediaID, '/tmp/');

//        var_dump();

        print_r($result);
    }


}