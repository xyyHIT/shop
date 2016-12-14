<?php
/**
 *    商品管理控制器
 */
class CycleIamgeApp extends BackendApp
{
    var $_goods_mod;

    /**
     * @var CycleImageModel
     */
    private $cycleImageModel = null;

    function __construct()
    {
        $this->GoodsApp();
    }
    function GoodsApp()
    {
        parent::BackendApp();

        $this->cycleImageModel =& m('CycleImage');
    }

    /* 商品列表 */
    function index()
    {
        $list = $this->cycleImageModel->getList();

        $this->assign('list', $list);

        $this->display('cycle_image.index.html');
    }

    function add()
    {
        if (!IS_POST)
        {
            $this->import_resource(array(
                'script' => 'jquery.plugins/jquery.validate.js'
            ));
            $this->display('recommend.form.html');
        }
        else
        {
            /* 检查名称是否已存在 */
            if (!$this->_recommend_mod->unique(trim($_POST['recom_name'])))
            {
                $this->show_warning('name_exist');
                return;
            }

            $data = array(
                'recom_name'   => $_POST['recom_name'],
            );

            $recom_id = $this->_recommend_mod->add($data);
            if (!$recom_id)
            {
                $this->show_warning($this->_recommend_mod->get_error());
                return;
            }

            $this->show_message('add_ok',
                'back_list',    'index.php?app=recommend',
                'continue_add', 'index.php?app=recommend&amp;act=add'
            );
        }
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
            $id = isset($_POST['id']) ? trim($_POST['id']) : '';
            if (!$id)
            {
                $this->show_warning('Hacking Attempt');
                return;
            }

            $recom_id = empty($_POST['recom_id']) ? 0 : intval($_POST['recom_id']);
            if (!$recom_id)
            {
                $this->show_warning('recommend_required');
                return;
            }

            $ids = explode(',', $id);
            $recom_mod =& bm('recommend', array('_store_id' => 0));
            $recom_mod->createRelation('recommend_goods', $recom_id, $ids);
            $ret_page = isset($_GET['ret_page']) ? intval($_GET['ret_page']) : 1;
            $this->show_message('recommend_ok',
                'back_list', 'index.php?app=goods&page=' . $ret_page,
                'view_recommended_goods', 'index.php?app=recommend&amp;act=view_goods&amp;id=' . $recom_id);
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

       if (in_array($column ,array('goods_name', 'brand', 'closed')))
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
}

?>
