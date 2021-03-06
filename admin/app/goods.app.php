<?php
/**
 *    商品管理控制器
 */
class GoodsApp extends BackendApp
{
    var $_goods_mod;

    function __construct()
    {
        $this->GoodsApp();
    }
    function GoodsApp()
    {
        parent::BackendApp();

        $this->_goods_mod =& m('goods');
    }

    /* 商品列表 */
    function index()
    {
        // 每页显示条数
        $items_per_arr = [10, 20, 50, 100];
        $items_per = empty($_GET['items_per']) ? 0 : intval($_GET['items_per']);

        $conditions = $this->_get_query_conditions(
            [
                [
                    'field' => 'goods_name',
                    'equal' => 'like',
                ],
                [
                    'field' => 'store_name',
                    'equal' => 'like',
                ],
                [
                    'field' => 'brand',
                    'equal' => 'like',
                ],
                [
                    'field' => 'closed',
                    'type'  => 'int',
                ]
            ]
        );

        // 上下架
        $if_show = empty($_GET['if_show']) ? 0 : intval($_GET['if_show']);
        if($if_show == 1){
            $conditions .= " AND if_show = 1 AND closed = 0";
        }else if($if_show == 2){
            $conditions .= " AND if_show <> 1 ";
        }

        // 分类
        $cate_id = empty($_GET['cate_id']) ? 0 : intval($_GET['cate_id']);
        if ($cate_id > 0)
        {
            $cate_mod =& bm('gcategory');
            $cate_ids = $cate_mod->get_descendant_ids($cate_id);
            $conditions .= " AND cate_id" . db_create_in($cate_ids);
        }

        $page = $this->_get_page($items_per_arr[$items_per]);
        $goods_list = $this->_goods_mod->get_list(array(
            'conditions' => "1 = 1" . $conditions,
            'count' => true,
            'order' => "g.sort = 0,g.sort asc,gst.score desc",
            'limit' => $page['limit'],
        ),$scate_ids = [], $desc = false, $no_picture = true,$admin = true);
        foreach ($goods_list as $key => $goods)
        {
            $goods_list[$key]['cate_name'] = $this->_goods_mod->format_cate_name($goods['cate_name']);
        }

        $this->assign('goods_list', $goods_list);
        $page['item_count'] = $this->_goods_mod->getCount();
        $this->_format_page($page);
        $page['items_per_arr'] = $items_per_arr;
        $page['items_per'] = $items_per;
        $this->assign('page_info', $page);

        // 第一级分类
        $cate_mod =& bm('gcategory', array('_store_id' => 0));

        $this->assign('gcategories', $cate_mod->get_options(0, true));
        $this->assign('enable_radar', Conf::get('enable_radar'));

        $this->import_resource(array('script' => 'mlselection.js,inline_edit.js'));

        $this->display('goods.index.html');
    }

    /* 推荐商品到 */
    function recommend()
    {
        if (!IS_POST)
        {
            /* 取得推荐类型 */
            $recommend_mod =& bm('recommend', array('_store_id' => 0));
            $recommends = $recommend_mod->get_options();
            if (!$recommends)
            {
                $this->show_warning('no_recommends', 'go_back', 'javascript:history.go(-1);', 'set_recommend', 'index.php?app=recommend');
                return;
            }
            $this->assign('recommends', $recommends);
            $this->display('goods.batch.html');
        }
        else
        {
            $goodsIdsStr = isset($_POST['id']) ? trim($_POST['id']) : '';
            if (!$goodsIdsStr)
            {
                $this->show_warning('Hacking Attempt');
                return;
            }

            $recommendID = empty($_POST['recom_id']) ? 0 : intval($_POST['recom_id']);
            if (!$recommendID)
            {
                $this->show_warning('recommend_required');
                return;
            }

            $goodsIds = explode(',', $goodsIdsStr);
            $goodsModel = & m('goods');
            $recommendMod = & bm('recommend', array('_store_id' => 0));

            // 先删除
            $recommendMod->db->query("delete from ecm_recommended_goods where goods_id " . db_create_in($goodsIds));

            // 再新增
            $recommendMod->createRelation('recommend_goods', $recommendID, $goodsIds);
            $goodsModel->edit($goodsIds,['recommended'=>'1']);

            $businessImageModel = &m('BusinessImage');
            $businessImageModel->drop(" type='recommend' and fk_id ".db_create_in($goodsIds));

            // 默认推荐图为商品第一张图
            $goods_list = $this->_goods_mod->find(
                [
                    'conditions' => 'goods_id ' . db_create_in($goodsIds),
                ],
                $scate_ids = [],
                $desc = false,
                $no_picture = false,
                $admin = false
            );
            foreach ($goods_list as $key => $goods)
            {
                $data = [
                    'image_type'       => '',
                    'image_size'       => '',
                    'image_name'       => '',
                    'image_url'        => $goods['default_image'],
                    'cloud_image_id'   => '',
                    'cloud_image_data' => '',
                    'fk_id'            => $goods['goods_id'],
                    'type'             => 'recommend',
                ];

                $businessImageModel->add($data);
            }

            $ret_page = isset($_GET['ret_page']) ? intval($_GET['ret_page']) : 1;
            $this->show_message('recommend_ok',
                'back_list', 'index.php?app=goods&page=' . $ret_page,
                'view_recommended_goods', 'index.php?app=recommend&amp;act=view_goods&amp;id=' . $recommendID);
        }
    }

    /* 编辑商品 */
    function edit()
    {
        if (!IS_POST)
        {
            // 第一级分类
            $cate_mod =& bm('gcategory', array('_store_id' => 0));
            $this->assign('gcategories', $cate_mod->get_options(0, true));

            $this->headtag('<script type="text/javascript" src="{lib file=mlselection.js}"></script>');
            $this->display('goods.batch.html');
        }
        else
        {
            $id = isset($_POST['id']) ? trim($_POST['id']) : '';
            if (!$id)
            {
                $this->show_warning('Hacking Attempt');
                return;
            }

            $ids = explode(',', $id);
            $data = array();
            if ($_POST['cate_id'] > 0)
            {
                $data['cate_id'] = $_POST['cate_id'];
                $data['cate_name'] = $_POST['cate_name'];
            }
            if (trim($_POST['brand']))
            {
                $data['brand'] = trim($_POST['brand']);
            }
            if ($_POST['closed'] >= 0)
            {
                $data['closed'] = $_POST['closed'] ? 1 : 0;
                $data['close_reason'] = $_POST['closed'] ? $_POST['close_reason'] : '';
            }

            if (empty($data))
            {
                $this->show_warning('no_change_set');
                return;
            }

            $this->_goods_mod->edit($ids, $data);
            $ret_page = isset($_GET['ret_page']) ? intval($_GET['ret_page']) : 1;
            $this->show_message('edit_ok',
                'back_list', 'index.php?app=goods&page=' . $ret_page);
        }
    }

    //异步修改数据
   function ajax_col()
   {
       $id     = empty($_GET['id']) ? 0 : intval($_GET['id']);
       $column = empty($_GET['column']) ? '' : trim($_GET['column']);
       $value  = isset($_GET['value']) ? trim($_GET['value']) : '';
       $data   = array();

       if (in_array($column ,array('goods_name', 'brand', 'closed','sort')))
       {
           $data[$column] = $value;
           $this->_goods_mod->edit($id, $data);
           if(!$this->_goods_mod->has_error())
           {
               echo ecm_json_encode(true);
           }
       }
       else
       {
           return ;
       }
       return ;
   }

    /* 删除商品 */
    function drop()
    {
        if (!IS_POST)
        {
            $this->display('goods.batch.html');
        }
        else
        {
            $id = isset($_POST['id']) ? trim($_POST['id']) : '';
            if (!$id)
            {
                $this->show_warning('Hacking Attempt');
                return;
            }
            $ids = explode(',', $id);

            // notify store owner
            $ms =& ms();
            $goods_list = $this->_goods_mod->find(array(
                "conditions" => $ids,
                "fields" => "goods_name, store_id",
            ));
            foreach ($goods_list as $goods)
            {
                //$content = sprintf(LANG::get('toseller_goods_droped_notify'), );
                $content = get_msg('toseller_goods_droped_notify', array('reason' => trim($_POST['drop_reason']),
                    'goods_name' => addslashes($goods['goods_name'])));
                $ms->pm->send(MSG_SYSTEM, $goods['store_id'], '', $content);
            }

            // drop
            $this->_goods_mod->drop_data($ids);
            $this->_goods_mod->drop($ids);
            $ret_page = isset($_GET['ret_page']) ? intval($_GET['ret_page']) : 1;
            $this->show_message('drop_ok',
                'back_list', 'index.php?app=goods&page=' . $ret_page);
        }
    }
	
	    /**
     *    查看
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function view()
    {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$id)
        {
            $this->show_warning('no_such_goods');

            return;
        }
		$goods_mod = & m('goods');
		$data = [ 'id' => $id ];
		/* 商品信息 */
		$goods = $goods_mod->get_info($id);
		$goods['tags'] = $goods['tags'] ? explode(',', trim($goods['tags'], ',')) : [];
		$goods['description'] = $goods['description'] ? html_script_reverse($goods['description']) : '';
		$data['goods'] = $goods;
		/* 店铺信息 */
		if ( !$goods['store_id'] ) {
            $this->show_warning('no_such_goods');
            return;
		}
		$store_mod  =& m('store');
		$store_info = $store_mod->get_info($goods['store_id']);
		$data['store_data'] = $store_info;
		$data['goods']['add_time'] = empty($data['goods']['add_time'])?'':date('Y-m-d H:i',$data['goods']['add_time']);  
		$collect_store = $store_mod->getOne('select count(*) from ' . DB_PREFIX . "collect where type = 'store' and item_id=" . $goods['store_id']);
		$data['store_data']['collect'] = $collect_store;
		$this->assign('goods', $data['goods']);
		$this->assign('goods_specs', $data['goods']['_specs']['0']);
		$this->assign('goods_store', $data['store_data']);
        $this->display('goods.view.html');
    }
}

?>