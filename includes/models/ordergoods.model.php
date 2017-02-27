<?php

/* 订单商品 ordergoods */

class OrdergoodsModel extends BaseModel
{
    var $table = 'order_goods';
    var $prikey = 'rec_id';
    var $_name = 'ordergoods';
    var $_relation = [
        // 一个订单商品只能属于一个订单
        'belongs_to_order' => [
            'model'       => 'order',
            'type'        => BELONGS_TO,
            'foreign_key' => 'order_id',
            'reverse'     => 'has_ordergoods',
        ],

        // 一个订单商品有多个评价图片
        'has_uploadedfile' => [
            'model'       => 'uploadedfile',
            'type'        => HAS_MANY,
            'foreign_key' => 'item_id',
            'ext_limit'   => [ 'belong' => BELONG_EVALUATE ],
            'dependent'   => true
        ],

    ];
}

?>