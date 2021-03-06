<?php
/*******************************************************************************
 * 艺加商城
 *
 * (c)  2016  Gavin(田宇)  <tianyu_0723@hotmail.com>
 *
 ******************************************************************************/

/* 广告图,业务图,商品推荐图,分类轮播图 */
class BusinessImageModel extends BaseModel {
    var $table = 'business_image';
    var $prikey = 'image_id';
    var $_name = 'businessImage';

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
    public function getList($fields = 'i.*',$conditions = '',$order = '',$limit = ''){
        $conditions = $this->_getConditions($conditions, true);

        if ( $order ) {
            $order = " ORDER BY {$order} ";
        }
        $limit && $limit = ' LIMIT ' . $limit;
        # Todo... 这里库存数量是默认规格库存，不是所有商品
        $sql = "SELECT {$fields} FROM {$this->table} i left join ecm_goods g on i.fk_id = g.goods_id left join ecm_goods_spec s on g.default_spec = s.spec_id {$conditions}{$order}{$limit}";

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