<?php
/*******************************************************************************
 * 艺加商城
 *
 * (c)  2016  Gavin(田宇)  <tianyu_0723@hotmail.com>
 *
 ******************************************************************************/

/* 轮播图模型 */
class CycleImageModel extends BaseModel {
    var $table = 'cycle_image'; //
    var $prikey = 'image_id';
    var $_name = 'cycleImage';

    /**
     * 获取轮播图列表
     *
     * @return array
     */
    public function getList(){
        $list = [];

        $this->db->get

        return $list;
    }


    /* 清除缓存 */
    function clear_cache( $goods_id ) {
        $cache_server =& cache_server();
        $keys = [ 'page_of_goods_' . $goods_id ];
        foreach ( $keys as $key ) {
            $cache_server->delete($key);
        }
    }

    function edit( $conditions, $edit_data ) {
        /* 清除缓存 */
        $goods_list = $this->find([
            'fields'     => 'goods_id',
            'conditions' => $conditions,
        ]);
        foreach ( $goods_list as $goods ) {
            $this->clear_cache($goods['goods_id']);
        }

        // 根据cate_id取得cate_id_1到cate_id_4
        if ( is_array($edit_data) && isset( $edit_data['cate_id'] ) ) {
            $edit_data = array_merge($edit_data, $this->_get_cate_ids($edit_data['cate_id']));
        }

        return parent::edit($conditions, $edit_data);
    }

    function drop( $conditions, $fields = '' ) {
        /* 清除缓存 */
        $goods_list = $this->find([
            'fields'     => 'goods_id',
            'conditions' => $conditions,
        ]);
        foreach ( $goods_list as $goods ) {
            $this->clear_cache($goods['goods_id']);
        }
        /* 清除店铺商品数缓存 */
        $cache_server =& cache_server();
        $cache_server->delete('goods_count_of_store');

        return parent::drop($conditions, $fields);
    }

}


?>