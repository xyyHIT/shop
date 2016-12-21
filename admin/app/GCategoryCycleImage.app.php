<?php
/*******************************************************************************
 * 艺加商城
 *
 * (c)  2016  Gavin(田宇)  <tianyu_0723@hotmail.com>
 *
 ******************************************************************************/

/**
 *    分类轮播图
 */
class GCategoryCycleImageApp extends BackendApp
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

    /**
     * 某个分类的轮播图列表
     */
    public function index(){
        // 分类ID
        $cateID = empty($_REQUEST['cate_id']) ? 0 : intval($_REQUEST['cate_id']);

        /* 是否存在 */
        $sql = "select * from ecm_business_image i where i.fk_id = {$cateID} and i.type = 'category_cycle' order by sort asc ";
        $images = $this->cycleImageModel->getAll($sql);

        $this->assign('images', $images);
        $this->assign('cate_id',$cateID);

        $this->import_resource(array('script' => 'inline_edit.js'));

        $this->display('gcategory_cycle_image.index.html');
    }

    public function create(){
        $cateID = empty($_REQUEST['cate_id']) ? 0 : intval($_REQUEST['cate_id']);

        $this->assign('cate_id',$cateID);

        $this->display('gcategory_cycle_image.create.html');
    }

    public function store(){
        $cateID = empty($_REQUEST['cate_id']) ? 0 : intval($_REQUEST['cate_id']);
        $sort = empty($_REQUEST['sort']) ? 0 : intval($_REQUEST['sort']);

        if(!$cateID || $_FILES['image']['error'] > 0){
            $this->show_warning('上传文件为空或者文件错误');
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
            'type' => 'category_cycle',
            'fk_id' => $cateID,
            'sort' => $sort
        ];

        $imageID = $this->cycleImageModel->add($data);
        if ( !$imageID ) {
            $this->show_warning('上传保存时出错');
            return false;
        }

        $this->show_message('上传成功',
            'back_list',    "index.php?app=GCategoryCycleImage&amp;act=index&amp;cate_id={$cateID}&amp;cate_name=",
            'continue_add', "index.php?app=GCategoryCycleImage&amp;act=create&amp;cate_id={$cateID}"
        );


    }

    public function destroy(){
        $imageID = isset($_REQUEST['image_id']) ? trim($_REQUEST['image_id']) : '';
        $cateID = empty($_REQUEST['cate_id']) ? 0 : intval($_REQUEST['cate_id']);

        if(!$imageID){
            $this->show_message('未选取条目');
            return false;
        }

        $ids = explode(',',$imageID);
        $this->cycleImageModel->drop($ids);

        $this->show_message('删除成功',
            'back_list',    "index.php?app=GCategoryCycleImage&amp;act=index&amp;cate_id={$cateID}&amp;cate_name="
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


}

?>
