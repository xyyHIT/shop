<?php

/* 订单 order */
class OrderModel extends BaseModel
{
    var $table  = 'order';
    var $alias  = 'order_alias';
    var $prikey = 'order_id';
    var $_name  = 'order';
    var $_relation  = array(
        // 一个订单有一个实物商品订单扩展
        'has_orderextm' => array(
            'model'         => 'orderextm',
            'type'          => HAS_ONE,
            'foreign_key'   => 'order_id',
            'dependent'     => true
        ),
        // 一个订单有多个订单商品
        'has_ordergoods' => array(
            'model'         => 'ordergoods',
            'type'          => HAS_MANY,
            'foreign_key'   => 'order_id',
            'dependent'     => true
        ),
        // 一个订单有多个订单日志
        'has_orderlog' => array(
            'model'         => 'orderlog',
            'type'          => HAS_MANY,
            'foreign_key'   => 'order_id',
            'dependent'     => true
        ),
        'belongs_to_store'  => array(
            'type'          => BELONGS_TO,
            'reverse'       => 'has_order',
            'model'         => 'store',
        ),
        'belongs_to_user'  => array(
            'type'          => BELONGS_TO,
            'reverse'       => 'has_order',
            'model'         => 'member',
        ),
    );

    /**
     *    修改订单中商品的库存，可以是减少也可以是加回
     *
     *    @author    Garbin
     *    @param     string $action     [+:加回， -:减少]
     *    @param     int    $order_id   订单ID
     *    @return    bool
     */
    function change_stock($action, $order_id)
    {
        if (!in_array($action, array('+', '-')))
        {
            $this->_error('undefined_action');

            return false;
        }
        if (!$order_id)
        {
            $this->_error('no_such_order');

            return false;
        }

        /* 获取订单商品列表 */
        $model_ordergoods =& m('ordergoods');
        $order_goods = $model_ordergoods->find("order_id={$order_id}");
        if (empty($order_goods))
        {
            $this->_error('goods_empty');

            return false;
        }

        $model_goodsspec =& m('goodsspec');
        $model_goods =& m('goods');

        /* 依次改变库存 */
        foreach ($order_goods as $rec_id => $goods)
        {
            $model_goodsspec->edit($goods['spec_id'], "stock=stock {$action} {$goods['quantity']}");
            $model_goods->clear_cache($goods['goods_id']);
        }

        /* 操作成功 */
        return true;
    }
	
	/**
     *    修改订单中商品的库存，可以是减少也可以是加回 
     *
     *    @author    newrain
     *    @param     string $action     [+:加回， -:减少]
     *    @param     array  $orderidarr   订单ID数组	 
			Array
			(
				[0] => Array
					(
						[order_id] => 8
						[order_sn] => 1632029260
					)
				[1] => Array
					(
						[order_id] => 9
						[order_sn] => 1632084375
					)
			)
     *    @return    bool
     */
    function ejchange_stock($action, $orderidarr = array())
    {
        if (!in_array($action, array('+', '-')))
        {
            return false;
        }
        if (!$orderidarr)
        {
            return false;
        }
		$endarr = end($orderidarr);
		$orderidin = '';
		foreach($orderidarr as $value){
			$orderidin .= $value['order_id'];
			if($value != $endarr){
				$orderidin .= ',';
			}
		}
        /* 获取订单商品列表 */
        $model_ordergoods =& m('ordergoods');
        $order_goods = $model_ordergoods->db->getAll("select rec_id,goods_id,quantity,spec_id from ".DB_PREFIX."order_goods where order_id in (".$orderidin.")");
        if (empty($order_goods))
        {
            return false;
        }
        $model_goodsspec =& m('goodsspec');
        $model_goods =& m('goods');
        /* 依次改变库存 */
        foreach ($order_goods as $rec_id => $goods)
        {
            $model_goodsspec->edit($goods['spec_id'], "stock=stock {$action} {$goods['quantity']}");
            $model_goods->clear_cache($goods['goods_id']);
        }
        /* 操作成功 */
        return true;
    }
}

?>
