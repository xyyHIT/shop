<?php

class DefaultApp extends MallbaseApp // MemberbaseApp MallbaseApp
{

    /**
     * @var CycleImageModel
     */
    private $businessImageModel = null;

    /**
     * @var RecommendModel
     */
    private $recommendModel = null;

    /**
     * 获取首页信息
     *
     * by Gavin 20161124
     */
    function ejIndex()
    {
        # Todo 缓存...
        // 取得分类
        $categoryArr = $this->_CategoryList(true);

        // 获取轮播图
        $cycleImageArr = $this->_ejCycleImages();

        $allArr = [
            'categories'  => $categoryArr,
            'cycleImages' => $cycleImageArr,
        ];

        return $this->ej_json_success($allArr);
    }

    /**
     * 首页每日好货
     */
    public function ejRecommend()
    {
        $page = $this->_get_page(3);

        $this->recommendModel = &m('recommend');

        $count = $this->recommendModel->getOne("SELECT COUNT(*) FROM ecm_recommend where is_publish = 1");
        $page['item_count'] = $count;

        $res = $this->recommendModel->db->query("select recom_id from ecm_recommend where is_publish = 1 order by sort asc limit {$page['limit']}");
        $recoms = [];
        while ( $row = $this->recommendModel->db->fetchRow($res) ) {
            $recoms[ $row['recom_id'] ] = [];
        }

        # Todo 缓存...

        /* 推荐商品 */
        $sql = "SELECT rg.recom_id,g.goods_id, g.goods_name, g.default_image, gs.price, gs.stock, i.image_url " .
            "FROM " . DB_PREFIX . "recommended_goods AS rg " .
            "   LEFT JOIN " . DB_PREFIX . "goods AS g ON rg.goods_id = g.goods_id " .
            "   LEFT JOIN " . DB_PREFIX . "goods_spec AS gs ON g.default_spec = gs.spec_id " .
            "   LEFT JOIN " . DB_PREFIX . "store AS s ON g.store_id = s.store_id " .
            "   LEFT JOIN " . DB_PREFIX . "business_image AS i on i.fk_id = g.goods_id and i.type = 'recommend' " .
            "WHERE g.if_show = 1 AND g.closed = 0 AND s.state = 1 " .
            "AND rg.recom_id " . db_create_in(array_keys($recoms)) .
            "AND g.goods_id IS NOT NULL " .
            "ORDER BY rg.sort_order ";

        $res = $this->recommendModel->db->query($sql);
        while ( $row = $this->recommendModel->db->fetchRow($res) ) {
            empty( $row['default_image'] ) && $row['default_image'] = Conf::get('default_goods_image');
            $row['image_url'] && $row['default_image'] = $row['image_url'];
            $recoms[ $row['recom_id'] ][] = $row;
        }

        $result = [
            'daily' => array_values($recoms), // 返回的每日好货数据
            'page'  => $page, // 分页信息
        ];

        return $this->ej_json_success($result);
    }

    function index()
    {
        $this->assign('index', 1); // 标识当前页面是首页，用于设置导航状态
        $this->assign('icp_number', Conf::get('icp_number'));

        /* 热门搜素 */
        $this->assign('hot_keywords', $this->_get_hot_keywords());

        $this->_config_seo([
            'title' => Lang::get('mall_index') . ' - ' . Conf::get('site_title'),
        ]);
        $this->assign('page_description', Conf::get('site_description'));
        $this->assign('page_keywords', Conf::get('site_keywords'));

        $this->display('/index/index.html');
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
     * 获取轮播图
     *
     * by Gavin 20161215
     */
    public function _ejCycleImages()
    {
        $this->businessImageModel =& m('BusinessImage');

        $fields = 'i.image_id,i.image_url,i.image_link,i.image_name';

        $data = $this->businessImageModel->getList($fields, " i.type='cycle' and CURDATE() >= i.start_at and CURDATE() <= i.end_at ", 'sort asc');

        return $data;
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
