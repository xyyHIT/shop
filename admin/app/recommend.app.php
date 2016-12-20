<?php

class RecommendApp extends BackendApp
{
    var $_recommend_mod;

    function __construct()
    {
        $this->RecommendApp();
    }

    function RecommendApp()
    {
        parent::BackendApp();

        $this->_recommend_mod =& bm('recommend', array('_store_id' => 0));
    }

    function index()
    {
        $conditions = $this->_get_query_conditions(array(
            array(
                'field' => 'recom_name',
                'equal' => 'LIKE',
            ),
        ));

        $page = $this->_get_page();
        $recommends = $this->_recommend_mod->find(array(
            'conditions' => '1=1' . $conditions,
            'count' => true,
            'order' => 'recom_id desc',
            'limit' => $page['limit'],
        ));
        $count = $this->_recommend_mod->count_goods();
        foreach ($recommends as $key => $recommend)
        {
            $recommends[$key]['goods_count'] = $count[$recommend['recom_id']];
        }
        $this->assign('recommends', $recommends);

        $page['item_count'] = $this->_recommend_mod->getCount();
        $this->_format_page($page);
        $this->assign('filtered', $conditions? 1 : 0); //是否有查询条件
        $this->assign('page_info', $page);
        /* 导入jQuery的表单验证插件 */
        $this->import_resource(array(
            'script' => 'jqtreetable.js',
            'style'  => 'res:style/jqtreetable.css'
        ));
        $this->display('recommend.index.html');
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

    /* 检查商品推荐的唯一性 */
    function check_recom()
    {
        $recom_name = empty($_GET['recom_name']) ? '' : trim($_GET['recom_name']);
        $recom_id   = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!$recom_name) {
            echo ecm_json_encode(false);
            return ;
        }
        if ($this->_recommend_mod->unique($recom_name, $recom_id)) {
            echo ecm_json_encode(true);
        }
        else
        {
            echo ecm_json_encode(false);
        }
        return;
    }

    function edit()
    {
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!IS_POST)
        {
            /* 是否存在 */
            $recommend = $this->_recommend_mod->get_info($id);
            if (!$recommend)
            {
                $this->show_warning('recommend_empty');
                return;
            }
            $this->import_resource(array(
                 'script' => 'jquery.plugins/jquery.validate.js'
            ));
            $this->assign('recommend', $recommend);

            $this->display('recommend.form.html');
        }
        else
        {
            /* 检查名称是否已存在 */
            if (!$this->_recommend_mod->unique(trim($_POST['recom_name']), $id))
            {
                $this->show_warning('name_exist');
                return;
            }

            $data = array(
                'recom_name'   => $_POST['recom_name'],
            );

            $this->_recommend_mod->edit($id, $data);
            $this->show_message('edit_ok',
                'back_list',    'index.php?app=recommend',
                'edit_again',   'index.php?app=recommend&amp;act=edit&amp;id=' . $id
            );
        }
    }

    function drop()
    {
        $id = isset($_GET['id']) ? trim($_GET['id']) : '';
        if (!$id)
        {
            $this->show_warning('no_recommend_to_drop');
            return;
        }

        $ids = explode(',', $id);
        if (!$this->_recommend_mod->drop($ids))
        {
            $this->show_warning($this->_recommend_mod->get_error());
            return;
        }

        $this->show_message('drop_ok');
    }

    /* 查看推荐类型下的商品 */
    function view_goods()
    {
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!$id)
        {
            $this->show_warning('Hacking Attempt');
            return;
        }

        /* 取得推荐类型 */
        $recommends = $this->_recommend_mod->get_options();
        if (!$recommends[$id])
        {
            $this->show_warning('Hacking Attempt');
            return;
        }
        $this->assign('recommends', $recommends);

        /* 取得推荐商品 */
        $page = $this->_get_page();
        $goods_mod =& m('goods');
        $goods_list = $goods_mod->find(array(
            'join' => 'be_recommend, belongs_to_store, has_goodsstatistics',
            'fields' => 'g.goods_name, s.store_id, s.store_name, g.cate_name, g.brand, recommended_goods.sort_order, g.closed, g.if_show, views',
            'conditions' => "recommended_goods.recom_id = '$id'",
            'limit' => $page['limit'],
            'order' => 'recommended_goods.sort_order',
            'count' => true,
        ));

        $businessImageModel = & m('BusinessImage');
        foreach ($goods_list as $key => $goods)
        {
            $image = $businessImageModel->get([
                "conditions" => "type = 'recommend' and fk_id = {$goods['goods_id']}"
            ]);
            $goods_list[$key]['recommend_image'] = $image ? $image['image_url'] : '';
            $goods_list[$key]['cate_name'] = $goods_mod->format_cate_name($goods['cate_name']);
        }
        $this->assign('goods_list', $goods_list);

        $page['item_count'] = $goods_mod->getCount();
        $this->_format_page($page);
        $this->assign('page_info', $page);

        $this->import_resource(array('script' => 'inline_edit.js'));
        $this->display('recommend.goods.html');
    }

    /* 取消推荐 */
    function drop_goods_from()
    {
        if (empty($_GET['id']) || empty($_GET['goods_id']))
        {
            $this->show_warning('Hacking Attempt');
            return;
        }

        $id = intval($_GET['id']);
        $goods_ids = explode(',', $_GET['goods_id']);
        $this->_recommend_mod->unlinkRelation('recommend_goods', $id, $goods_ids);

        $goodsModel = & m('goods');
        $goodsModel->edit($goods_ids,['recommended'=>'0']);

        $this->show_message('drop_goods_from_ok');
    }

    // 异步修改数据
    function ajax_col()
    {
        $id     = $_GET['id'];
        $column = empty($_GET['column']) ? '' : trim($_GET['column']);
        $value  = intval($_GET['value']);
        $data   = array();
        $arr    = explode('-', $id);
        $recom_id = intval($arr[0]);
        $goods_id = intval($arr[1]);

        if (in_array($column ,array('sort_order')))
        {
            $data[$column] = $value;
            $this->_recommend_mod->createRelation('recommend_goods', $recom_id, array($goods_id => array('sort_order' => $value)));
            if(!$this->_recommend_mod->has_error())
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

    /**
     * 编辑商品推荐图
     *
     */
    public function editRecommendImage(){
        $goodsID = empty($_GET['goods_id']) ? 0 : intval($_GET['goods_id']); // 商品ID
        /* 是否存在 */
        $businessImageModel = & m('BusinessImage');
        $image = $businessImageModel->get([
            "conditions" => "type = 'recommend' and fk_id = {$goodsID}"
        ]);

        $image['goods_id'] = $goodsID;

        $this->assign('image', $image);
        $this->display('recommend_goods_image.edit.html');
    }

    /**
     * 更新商品推荐图
     *
     * @return bool
     */
    public function updateRecommendImage(){
        $goodsID = isset($_REQUEST['goods_id']) ? trim($_REQUEST['goods_id']) : '';
        if(!$goodsID){
            $this->show_message('未选取条目');
            return false;
        }

        // 只有上传图片时,才更新
        if($_FILES['image']['error'] == 0){
            $fileUrl = $_FILES["image"]["tmp_name"];
            // 必须是图片
            $mimeType = image_type_to_mime_type(exif_imagetype($fileUrl));
            if(!in_array($mimeType,['image/gif','image/jpeg','image/png','image/bmp'])){
                $this->show_warning('图片类型不正确,只支持 gif,jpeg,png,bmp 类型的图片');
                return false;
            }

            // 从临时目录拿到文件 上传到万象优图
            $cloudRetArr = \Tencentyun\ImageV2::upload($fileUrl, CLOUD_IMAGE_BUCKET);
            if ( $cloudRetArr['httpcode'] != 200 ) {
                $this->show_warning('上传服务器腾讯云服务器出错,请重试或联系技术人员');
                return false;
            }

            $data = [
                'image_type' => $mimeType,
                'image_size' => filesize($fileUrl),
                'image_name' => $_FILES['image']['name'],
                'image_url' => $cloudRetArr['data']['downloadUrl'],
                'cloud_image_id' => $cloudRetArr['data']['fileid'],
                'cloud_image_data' => json_encode($cloudRetArr),
                'fk_id' => $goodsID,
                'type' => 'recommend',
            ];

            // 业务图模型
            $businessImageModel = & m('BusinessImage');
            $image = $businessImageModel->get([
                "conditions" => "type = 'recommend' and fk_id = {$goodsID}"
            ]);

            if($image){
                // 如果已经存在就更新
                $isTrue = $businessImageModel->update($image['image_id'],$data);
            }else{
                // 没有就新增
                $isTrue = $businessImageModel->add($data);
            }

            if ( !$isTrue ) {
                $this->show_warning('失败');
                return false;
            }
        }


        $this->show_message('更新成功',
            'back_list',    'index.php?app=recommend'
        );
    }

    /**
     * 删除商品推荐图
     */
    public function destroyRecommendImage(){
        $id = isset($_REQUEST['goods_id']) ? $_REQUEST['goods_id'] : '';
        if(!$id){
            $this->show_message('未选取条目');
            return false;
        }

        $ids = explode(',',$id);

        $recommendImageModel = & m('BusinessImage');
        $affectedRows = $recommendImageModel->drop("type='recommend' and fk_id".db_create_in($ids));

        if($affectedRows){
            $this->show_message('删除成功');
        }else{
            $this->show_message('删除失败');
        }

    }


}

?>