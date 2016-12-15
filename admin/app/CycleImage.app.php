<?php
/**
 *    轮播图管理
 */
class CycleImageApp extends BackendApp
{
    /**
     * @var CycleImageModel
     */
    private $cycleImageModel = null;

    function __construct()
    {
        $this->app();
    }

    function app()
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

//    //异步修改数据
//   function ajax_col()
//   {
//       $id     = empty($_GET['id']) ? 0 : intval($_GET['id']);
//       $column = empty($_GET['column']) ? '' : trim($_GET['column']);
//       $value  = isset($_GET['value']) ? trim($_GET['value']) : '';
//       $data   = array();
//
//       if (in_array($column ,array('goods_name', 'brand', 'closed')))
//       {
//           $data[$column] = $value;
//           $this->_goods_mod->edit($id, $data);
//           if(!$this->_goods_mod->has_error())
//           {
//               echo ecm_json_encode(true);
//           }
//       }
//       else
//       {
//           return ;
//       }
//       return ;
//   }
//

}

?>
