<?php

use Tencentyun\ImageV2;

class WechatApp extends MallbaseApp
{

    /**
     * *************** 非常非常重要 *****************
     *
     * 重定向到指定html(业务页)
     *
     * by Gavin  20161208
     */
    public function redirectHtml()
    {
        $modul = $_GET['modul'];
        $action = $_GET['action'];

        // 显示html内容
        $this->ejDisplay("/$modul/$action.html");
    }

    /**
     * 给拍卖提供回调地址(拍卖修改用户信息等操作...)
     *
     * by Gavin  20161208
     */
    public function notify()
    {
        $auctionID = $_GET['user_id'] ? $_GET['user_id'] : 0; // 拍卖用户ID
//Log::getLogger()->warning('拍卖回调...用户',[$auctionID]);
        # Todo 考虑redis队列更新...
        if ( $auctionID ) {
            $memberModel = &m('member');
            $user = $memberModel->get("auction_id='{$auctionID}'"); // 用户信息
//Log::getLogger()->warning('用户信息',[$user]);

            // 如果没有用户,直接返回
            if ( $user === false ) return;

            $openid = $user['openid'];// 微信openid

            $storeModel =& m('store');
            $store = $storeModel->get($user['user_id']); // 店铺信息
//Log::getLogger()->warning('店铺信息',[$store]);

            // 确保拍卖redis存在,不存在就需要重定向获取
            $auctionInfoArr = Cache::store(WECHAT_USERINFO_REDIS)->get($openid . '#SHOP');
//Log::getLogger()->warning('拍卖redis信息',[$auctionInfoArr]);

            Cache::store('default');// 使用完切换回default
            // 拍卖微信数据
            $wxUserInfoArr = $auctionInfoArr['wxUserInfo'] ? $auctionInfoArr['wxUserInfo'] : [];
            // 拍卖用户数据
            $userInfoArr = $auctionInfoArr['userInfo'] ? $auctionInfoArr['userInfo'] : [];

            // 如果用户redis信息为空,直接返回
            if ( empty( $wxUserInfoArr ) || empty( $userInfoArr ) ) return;

            // 更新用户信息
            $memberModel->edit($user['user_id'], [
                'user_name' => $wxUserInfoArr['nickname'],
                'gender'    => $wxUserInfoArr['sex'],
                'portrait'  => $wxUserInfoArr['avatar'],
            ]);

            /**
             * 是否开启店铺
             * 更新数据
             */
            if ( intval($userInfoArr['vip']) == 0 && $store ) {
//Log::getLogger()->warning('取消vip');
                $storeModel->edit($user['user_id'], [
                    'state'        => 0,
                    'close_reason' => 'vip已取消',
                ]);
            } else if ( intval($userInfoArr['vip']) == 1 ) {
                if ( !$store ) {
//Log::getLogger()->warning('新增vip');
                    $data = [
                        'store_id'    => $user['user_id'],
                        'store_name'  => $userInfoArr['name'],
                        'owner_name'  => $userInfoArr['name'],
                        'owner_card'  => '',
                        'region_id'   => '',
                        'region_name' => $wxUserInfoArr['province'],
                        'address'     => $wxUserInfoArr['country'] . $wxUserInfoArr['province'] . $wxUserInfoArr['city'],
                        'zipcode'     => '',
                        'tel'         => '',
                        'store_logo'  => $wxUserInfoArr['avatar'],
                        'sgrade'      => 1, // 店铺等级ID
                        'state'       => 1, // 需要审核 0 ,不需要审核 1
                        'add_time'    => gmtime(),
                    ];
                    $storeModel->add($data);
                } else if ( $store['state'] == 0 ) {
//Log::getLogger()->warning('更新vip');
                    $storeModel->edit($user['user_id'], [
                        'state' => 1
                    ]);
                }
            }

        }

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

    /**
     * 仅供测试使用
     */
    public function test(){
//        Cache::store(WECHAT_USERINFO_REDIS)->rm('o55jRw7oEmeXiqZ8IyqWFDckRPh8#SHOP');
//        auction_user('00011607201018IBkvxulq','o55jRw7oEmeXiqZ8IyqWFDckRPh8');
    }
    /**
     * 写日志
     */
//$logger = new \Monolog\Logger('ej');
//$logger->pushHandler(new \Monolog\Handler\StreamHandler(ROOT_PATH.'/temp/debug.log',\Monolog\Logger::WARNING));
//$logger->warning('logger.....');

}