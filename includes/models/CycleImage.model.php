<?php
/*******************************************************************************
 * 艺加商城
 *
 * (c)  2016  Gavin(田宇)  <tianyu_0723@hotmail.com>
 *
 ******************************************************************************/

/* 轮播图模型 */
class CycleImageModel extends BaseModel {
    var $table = 'cycle_image';
    var $prikey = 'image_id';
    var $_name = 'cycleImage';

    /**
     * 获取轮播图列表
     *
     * @param string $fields
     * @param string $conditions
     * @param string $order
     * @param string $limit
     *
     * @return mixed
     */
    public function getList($fields = '*',$conditions = '',$order = '',$limit = ''){
        $conditions = $this->_getConditions($conditions, true);

        if ( $order ) {
            $order = " ORDER BY {$order} ";
        }
        $limit && $limit = ' LIMIT ' . $limit;

        $sql = "SELECT {$fields} FROM {$this->table}{$conditions}{$order}{$limit}";

        $list = $this->db->getAll($sql);

        return $list;
    }

    function update( $id, $data ) {
        # Todo 考虑缓存 删除缓存....

        // 先删除远程图片
        $imageArr = $this->find([
            'fields'     => 'image_id,cloud_image_id',
            'conditions' => $id,
        ]);
        foreach ($imageArr as $image){
            if(isset($data['image_url'])){
                \Tencentyun\ImageV2::del(CLOUD_IMAGE_BUCKET,$image['cloud_image_id']);
            }
        }
        // 调用父类更新操作
        return parent::edit($id, $data);
    }

    public function drop( $ids = null) {
        // 删除远程图片
        $imageArr = $this->find([
            'fields'     => 'image_id,cloud_image_id',
            'conditions' => $ids,
        ]);

        foreach ($imageArr as $image){
            \Tencentyun\ImageV2::del(CLOUD_IMAGE_BUCKET,$image['cloud_image_id']);
        }

        # Todo 考虑缓存 删除缓存....

        // 调用父类删除操作
        return parent::drop($ids, '');
    }

    /* 清除缓存 */
    function clearCache() {

    }

}


?>