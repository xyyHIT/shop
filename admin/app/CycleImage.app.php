<?php
/**
 *    轮播图管理
 */
class CycleImageApp extends BackendApp
{
    /**
     * @var BusinessImageModel
     */
    private $cycleImageModel = null;

    function __construct()
    {
        $this->app();
    }

    function app()
    {
        parent::BackendApp();

        $this->cycleImageModel =& m('BusinessImage');
    }

    /* 商品列表 */
    function index()
    {
        $list = $this->cycleImageModel->getList('*',"type='cycle'",'sort asc');

        $this->assign('list', $list);

        $this->import_resource(array('script' => 'inline_edit.js'));

        $this->display('cycle_image.index.html');
    }

    /**
     * 创建
     */
    public function create(){
        $this->import_resource([
            'script' => 'jquery.plugins/jquery.validate.js'
        ]);
        $this->display('cycle_image.create.html');
    }

    /**
     * 保存
     */
    public function store(){
        if($_FILES['image']['error'] > 0){
            $this->show_message('上传文件为空或者文件错误');
            return false;
        }

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

        /* 数据库保存 */
        $data = [
            'image_type' => $mimeType,
            'image_size' => filesize($fileUrl),
            'image_name' => $_FILES['image']['name'],
            'image_url' => $cloudRetArr['data']['downloadUrl'],
            'image_link' => $_GET['image_link'] ? $_GET['image_link'] : '',
            'cloud_image_id' => $cloudRetArr['data']['fileid'],
            'cloud_image_data' => json_encode($cloudRetArr),
            'type' => 'cycle'
        ];
        $imageID = $this->cycleImageModel->add($data);
        if ( !$imageID ) {
            $this->show_warning('上传保存时出错');
            return false;
        }

        $this->show_message('保存成功',
            'back_list',    'index.php?app=CycleImage',
            'continue_add', 'index.php?app=CycleImage&amp;act=create'
        );

    }

    /**
     * 编辑
     */
    public function edit(){
        $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        /* 是否存在 */
        $image = $this->cycleImageModel->get_info($id);
        if (!$image)
        {
            $this->show_warning('轮播图不存在');
            return;
        }
        $this->assign('image', $image);
        $this->display('cycle_image.edit.html');
    }

    /**
     * 更新
     */
    public function update(){
        $id = isset($_REQUEST['id']) ? trim($_REQUEST['id']) : '';
        if(!$id){
            $this->show_message('未选取条目');
            return false;
        }

        $data = [];
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
            ];
        }

        $data['image_link'] = $_REQUEST['image_link'] ? $_REQUEST['image_link'] : '';

        $imageID = $this->cycleImageModel->update($id,$data);
        if ( !$imageID ) {
            $this->show_warning('更新失败');
            return false;
        }

        $this->show_message('更新成功',
            'back_list',    'index.php?app=CycleImage'
        );
    }

    /**
     * 删除
     */
    public function destroy(){
        $id = isset($_REQUEST['id']) ? trim($_REQUEST['id']) : '';
        if(!$id){
            $this->show_message('未选取条目');
            return false;
        }

        $ids = explode(',',$id);
        $this->cycleImageModel->drop($ids);

        $this->show_message('删除成功',
            'back_list',    'index.php?app=CycleImage'
        );

    }

    //异步修改数据
   function ajax_col()
   {
       $id     = empty($_GET['id']) ? 0 : intval($_GET['id']);
       $column = empty($_GET['column']) ? '' : trim($_GET['column']);
       $value  = isset($_GET['value']) ? trim($_GET['value']) : '';
       $data   = array();

       if (in_array($column ,array('image_name', 'image_link','sort')))
       {
           $data[$column] = $value;
           $this->cycleImageModel->edit($id, $data);
           if(!$this->cycleImageModel->has_error())
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
     * 选择轮播图链接的商品
     */
   function select(){
       $conditions = $this->_get_query_conditions(array(
           array(
               'field' => 'goods_name',
               'equal' => 'like',
           ),
           array(
               'field' => 'store_name',
               'equal' => 'like',
           ),
           array(
               'field' => 'brand',
               'equal' => 'like',
           ),
           array(
               'field' => 'closed',
               'type'  => 'int',
           ),
       ));

       // 分类
       $cate_id = empty($_GET['cate_id']) ? 0 : intval($_GET['cate_id']);
       if ($cate_id > 0)
       {
           $cate_mod =& bm('gcategory');
           $cate_ids = $cate_mod->get_descendant_ids($cate_id);
           $conditions .= " AND cate_id" . db_create_in($cate_ids);
       }

       //更新排序
       if (isset($_GET['sort']) && isset($_GET['order']))
       {
           $sort  = strtolower(trim($_GET['sort']));
           $order = strtolower(trim($_GET['order']));
           if (!in_array($order,array('asc','desc')))
           {
               $sort  = 'goods_id';
               $order = 'desc';
           }
       }
       else
       {
           $sort  = 'goods_id';
           $order = 'desc';
       }

       $page = $this->_get_page();
       $goodsMod =& m('goods');
       $goods_list = $goodsMod->get_list(array(
           'conditions' => "1 = 1" . $conditions,
           'count' => true,
           'order' => "$sort $order",
           'limit' => $page['limit'],
       ),$scate_ids = [], $desc = false, $no_picture = true,$admin = true);
       foreach ($goods_list as $key => $goods)
       {
           $goods_list[$key]['cate_name'] = $goodsMod->format_cate_name($goods['cate_name']);
       }
       $this->assign('goods_list', $goods_list);

       $page['item_count'] = $goodsMod->getCount();
       $this->_format_page($page);
       $this->assign('page_info', $page);

       // 第一级分类
       $cate_mod =& bm('gcategory', array('_store_id' => 0));
       $this->assign('gcategories', $cate_mod->get_options(0, true));
       $this->import_resource(array('script' => 'mlselection.js'));


       $this->assign('cycle_image_id',$_GET['cycle_image_id']);
       $this->assign('cycle_type',$_GET['cycle_type']);

       $this->display('cycle_image_select.index.html');
   }

    /**
     * 设置轮播图链接地址
     */
   function setLink(){
       $imageID = $_GET['cycle_image_id'];
       $goodID = $_GET['goods_id'];
       $cycleType = $_GET['cycle_type'];

       $this->cycleImageModel->edit($imageID,['image_link' => SITE_URL.'/shop/html/index/goodsDetails.html?goodsId='.$goodID]);


       if($cycleType == 'cycle'){
           $this->show_message('设置轮播图链接成功','back_list', 'index.php?app=CycleImage');
       }else if($cycleType == 'category'){
           $this->show_message('设置轮播图链接成功','back_list', 'index.php?app=gcategory');
       }

   }

    /**
     * 获取查询条件
     *
     * @param $query_item
     *
     * @return string
     */
    function _get_query_conditions( $query_item )
    {
        $str = '';
        $query = [];
        foreach ( $query_item as $options ) {
            if ( is_string($options) ) {
                $field = $options;
                $options['field'] = $field;
                $options['name'] = $field;
            }
            !isset( $options['equal'] ) && $options['equal'] = '=';
            !isset( $options['assoc'] ) && $options['assoc'] = 'AND';
            !isset( $options['type'] ) && $options['type'] = 'string';
            !isset( $options['name'] ) && $options['name'] = $options['field'];
            !isset( $options['handler'] ) && $options['handler'] = 'trim';
            if ( isset( $_REQUEST[ $options['name'] ] ) ) {
                $input = $_REQUEST[ $options['name'] ];
                $handler = $options['handler'];
                $value = ( $input == '' ? $input : $handler($input) );
                if ( $value === '' || $value === false )  //若未输入，未选择，或者经过$handler处理失败就跳过
                {
                    continue;
                }
                strtoupper($options['equal']) == 'LIKE' && $value = "%{$value}%";
                if ( $options['type'] != 'numeric' ) {
                    $value = "'{$value}'";      //加上单引号，安全第一
                } else {
                    $value = floatval($value);  //安全起见，将其转换成浮点型
                }
                $str .= " {$options['assoc']} {$options['field']} {$options['equal']} {$value}";
                $query[ $options['name'] ] = $input;
            }
        }
        $this->assign('query', stripslashes_deep($query));

        return $str;
    }


}

?>
