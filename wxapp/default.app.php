<?php

class DefaultApp extends MallbaseApp
{

    /**
     * 获取首页信息
     *
     * by Gavin 20161124
     */
    function ejIndex()
    {
        // 取得分类
        $categoryArr = $this->_CategoryList(true);

        // 获取轮播图
        $cycleImageArr = $this->_GetCycleImages();

        // 获取推荐信息
        $recom_mod =& m('recommend');
        $recommendArr = $recom_mod->get_recommended_goods_all(6, true);

        $allArr = [
            'categories'  => $categoryArr,
            'cycleImages' => $cycleImageArr,
            'recommend'   => $recommendArr,
        ];

        return $this->ej_json_success($allArr);
    }

    function index2()
    {
//        echo json_encode($_SERVER);
        echo 'hello world';
    }

    function index()
    {
//        require ROOT_PATH . '/includes/Http.php';
//        $http = new Http();
//        $response = $http->get('http://devtst.yijiapai.com/yjpai/platform/user/getWxUser',[]);
//        $jsonArr = $http->parseJSON($http->get('http://devtst.yijiapai.com/yjpai/platform/user/getWxUser', []));
//        $response = $http->get('http://www.baidu.com',[]);
//        $content = $response->getBody();
//        echo $content;
//        exit(0);
//        echo 'hello,world !';
//        header('Location: http://www.baidu.com');

//        http://devtst.yijiapai.com?app=wechat&act=redirectRealPage&user_info=xxx&redirect_url=xxx

echo json_encode($_SESSION['openid']);
exit();

//        $redirect_url = 'http://devtst.yijiapai.com?app=default&act=index2';
//        $condition = "redirect_url=".urlencode($redirect_url);
//        header('Location: http://devtst.yijiapai.com/yjpai/platform/user/goShop?'.$condition);


        $arr = [ 'a' => '1', 'b' => '2' ];
        $this->assign('index', 1); // 标识当前页面是首页，用于设置导航状态
        $this->assign('icp_number', Conf::get('icp_number'));

        /* 热门搜素 */
        $this->assign('hot_keywords', $this->_get_hot_keywords());

        $this->_config_seo([
            'title' => Lang::get('mall_index') . ' - ' . Conf::get('site_title'),
        ]);
        $this->assign('page_description', Conf::get('site_description'));
        $this->assign('page_keywords', Conf::get('site_keywords'));
        $this->display('index.html');
    }

    function _get_hot_keywords()
    {
        $keywords = explode(',', conf::get('hot_search'));

        return $keywords;
    }

    /**
     * 获取首页轮播图
     *
     * @return array
     */
    public function _GetCycleImages()
    {
        $imagesArr = [];

        include_once( ROOT_PATH . '/includes/widget.base.php' );

        $widgets = get_widget_config('default', 'index');

        $widgetsID = $widgets['config']['cycle_image'][0];
        if ( isset( $widgetsID ) ) {
            $imagesArr = $widgets['widgets'][ $widgetsID ]['options'];
        }

        return $imagesArr;
    }

    /**
     * 取得分类信息
     *
     * @param bool $maxLevel 只去最大级别
     *
     * @return array
     */
    function _CategoryList( $maxLevel = false )
    {
        $cache_server =& cache_server();
        $key = 'page_goods_category';
        $data = $cache_server->get($key);
        if ( $data === false ) {
            $gcategory_mod =& bm('gcategory', [ '_store_id' => 0 ]);
            $gcategories = $gcategory_mod->get_list(-1, true);

            import('tree.lib');
            $tree = new Tree();
            $tree->setTree($gcategories, 'cate_id', 'parent_id', 'cate_name', 'imageurl');
            $data = $tree->ejgetArrayList(0);
            $cache_server->set($key, $data, 3600);
        }

        if ( $maxLevel ) {
            foreach ( $data as &$v ) {
                $v = [
                    'id'       => $v['id'],
                    'cateName' => $v['value'],
                    'imageURL' => $v['imageurl'],
                ];
            }
        }

        return $data;
    }
}

?>
