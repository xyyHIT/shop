<?php

/**
 *    普通订单类型
 *
 *    @author    Garbin
 *    @usage    none
 */
class NormalOrder extends BaseOrder
{
    var $_name = 'normal';

    /**
     *    查看订单
     *
     *    @author    Garbin
     *    @param     int $order_id
     *    @param     array $order_info
     *    @return    array
     */
    function get_order_detail($order_id, $order_info)
    {
        if (!$order_id)
        {
            return array();
        }

        /* 获取商品列表 */
        $data['goods_list'] =   $this->_get_goods_list($order_id);

        /* 配关信息 */
        $data['order_extm'] =   $this->_get_order_extm($order_id);

        /* 支付方式信息 */
        if ($order_info['payment_id'])
        {
            $payment_model      =& m('payment');
            $payment_info       =  $payment_model->get("payment_id={$order_info['payment_id']}");
            $data['payment_info']   =   $payment_info;
        }

        /* 订单操作日志 */
        $data['order_logs'] =   $this->_get_order_logs($order_id);

        return array('data' => $data);
    }

    /* 显示订单表单 */
    function get_order_form($store_id)
    {
        $data = array();
        $template = 'order.form.html';

        $visitor =& env('visitor');

        /* 获取我的收货地址 */
        $data['my_address']         = $this->_get_my_address($visitor->get('user_id'));
        $data['addresses']          =   ecm_json_encode($data['my_address']);
        $data['regions']            = $this->_get_regions();

        /* 配送方式 */
        $data['shipping_methods']   = $this->_get_shipping_methods($store_id);
        if (empty($data['shipping_methods']))
        {
            $this->_error('no_shipping_methods');

            return false;
        }
        $data['shippings']          = ecm_json_encode($data['shipping_methods']);
        foreach ($data['shipping_methods'] as $shipping)
        {
            $data['shipping_options'][$shipping['shipping_id']] = $shipping['shipping_name'];
        }

        return array('data' => $data, 'template' => $template);
    }

    /**
     *    提交生成订单，外部告诉我要下的单的商品类型及用户填写的表单数据以及商品数据，我生成好订单后返回订单ID
     *
     *    @author    Garbin
     *    @param     array $data
     *    @return    int
     */
    function submit_order($data)
    {
        /* 释放goods_info和post两个变量 */
        extract($data);
        /* 处理订单基本信息 */
        $base_info = $this->_handle_order_info($goods_info, $post);
        if (!$base_info)
        {
            /* 基本信息验证不通过 */

            return 0;
        }

        /* 处理订单收货人信息 */
        $consignee_info = $this->_handle_consignee_info($goods_info, $post);
        if (!$consignee_info)
        {
            /* 收货人信息验证不通过 */
            return 0;
        }

        /* 至此说明订单的信息都是可靠的，可以开始入库了 */

        /* 插入订单基本信息 */
        //订单总实际总金额，可能还会在此减去折扣等费用
        $base_info['order_amount']  =   $base_info['goods_amount'] + $consignee_info['shipping_fee'] - $base_info['discount'];
        
        /* 如果优惠金额大于商品总额和运费的总和 */
        if ($base_info['order_amount'] < 0)
        {
            $base_info['order_amount'] = 0;
            $base_info['discount'] = $base_info['goods_amount'] + $consignee_info['shipping_fee'];
        }
        $order_model =& m('order');
        $order_id    = $order_model->add($base_info);

        if (!$order_id)
        {
            /* 插入基本信息失败 */
            $this->_error('create_order_failed');

            return 0;
        }

        /* 插入收货人信息 */
        $consignee_info['order_id'] = $order_id;
        $order_extm_model =& m('orderextm');
        $order_extm_model->add($consignee_info);

        /* 插入商品信息 */
        $goods_items = array();
        foreach ($goods_info['items'] as $key => $value)
        {
            $goods_items[] = array(
                'order_id'      =>  $order_id,
                'goods_id'      =>  $value['goods_id'],
                'goods_name'    =>  $value['goods_name'],
                'spec_id'       =>  $value['spec_id'],
                'specification' =>  $value['specification'],
                'price'         =>  $value['price'],
                'quantity'      =>  $value['quantity'],
                'goods_image'   =>  $value['goods_image'],
            );
        }
        $order_goods_model =& m('ordergoods');
        $order_goods_model->add(addslashes_deep($goods_items)); //防止二次注入

        return $order_id;
    }
	
	   /**
     *    提交生成订单，外部告诉我要下的单的商品类型及用户填写的表单数据以及商品数据，我生成好订单后返回订单ID
     *	  修改   改为多订单提交
     *    @author    newrain   
     *    @param     array $data
     *    @return    int
     */
    function ejsubmit_order($data,$goodslist)
    {
        /* 释放goods_info和post两个变量 */
        extract($data);
        /* 处理订单基本信息 */
        $base_info = $this->ej_handle_order_info($goods_info, $post);
        if (!$base_info)
        {
            /* 基本信息验证不通过 */
            return 0;
        }
        /* 处理订单收货人信息 */
        $consignee_info = $this->ej_handle_consignee_info($goods_info, $post);
        if (!$consignee_info)
        {
            /* 收货人信息验证不通过 */
            return 0;
        }
        /* 至此说明订单的信息都是可靠的，可以开始入库了 以下注释层请查看对应的submit_order方法 */
        /* 插入订单基本信息 */
        //订单总实际总金额，可能还会在此减去折扣等费用
		/* 如果优惠金额大于商品总额和运费的总和 */
		$insertsql = '';
		$ordersnarr = array();
		$endarr = end($base_info);
		foreach($base_info as $value){
			$insertsql .= "('".$value['order_sn']."','".$value['type']."','".$value['extension']."','".
					$value['seller_id']."','".$value['seller_name']."','".$value['buyer_id']."','".
					$value['buyer_name']."','".$value['buyer_email']."','".$value['status']."','".
					$value['add_time']."','".$value['goods_amount']."','".$value['order_amount']."','".
					$value['discount']."','".$value['anonymous']."','".$value['postscript']."')";
			if($endarr != $value){
				$insertsql .= ',';
			}
			array_push($ordersnarr,$value['order_sn']);
		}
		//保证效率采用多条插入的方式
		/*适当时机加上事务和锁*/
        $order_model =& m('order');
		$sqlfields = 'order(order_sn,type,extension,seller_id,seller_name,buyer_id,buyer_name,buyer_email,status,add_time,goods_amount,order_amount,discount,anonymous,postscript)';
		$order_model->db->query('INSERT INTO '.DB_PREFIX.$sqlfields.' VALUES'.$insertsql);
		//采取办法获取orderid
		$orderidarr = $order_model->getAll("select order_id,order_sn,seller_id from ".DB_PREFIX."order where order_sn in (".implode(',',$ordersnarr).")");
        if (empty($orderidarr))
        {
            return 0;
        }
        /* 插入收货人信息 */
		$inconsignee = '';
		$endarr = end($orderidarr);
		foreach($orderidarr as $value){
			$inconsignee .= "('".$value['order_id']."','".$consignee_info['consignee']."','".$consignee_info['region_id']."','".
				$consignee_info['region_name']."','".$consignee_info['address']."','".$consignee_info['phone_mob']."','".
				$consignee_info['shipping_fee'][$value['seller_id']]."')";
				//获取storeid  匹配orderid方便订单商品操作
				$goodorder[$value['seller_id']] = $value['order_id'];
				//获取订单id与订单号匹配  为后续统计总订单提供支持
				$sumorderarr[$value['order_id']] = $value['order_sn'];
			if($endarr != $value){
				$inconsignee .= ',';
			}
		}
		$sqlfields = 'order_extm(order_id,consignee,region_id,region_name,address,phone_mob,shipping_fee)';
		$order_model->db->query('INSERT INTO '.DB_PREFIX.$sqlfields.' VALUES'.$inconsignee);
		/*插入总订单表*/
		if(!$sumorderarr){
			return 0;
		}
		$sumorderjson = json_encode($sumorderarr);
		$sqlfields = 'sumorder(orderid,addtime,userid,ordersn)';
		/* 买家信息 */
		$visitor     =& env('visitor');
        $user_id     =  $visitor->get('user_id');
		$sumordersn = '1'.$this->_gen_order_sn();//合单前三位为110
		$order_model->db->query('INSERT INTO '.DB_PREFIX.$sqlfields." VALUES('".$sumorderjson."','".time()."','".$user_id."','".$sumordersn."')");
		$sumorderid = $order_model->db->insert_id();
	   /* 插入商品信息 */
		if(empty($goodslist)){
			return 0;
		}
		$endarr = end($goodslist);
		$inordergoods = '';
        foreach ($goodslist as $key => $value)
        {
			$inordergoods .= "('".$goodorder[$value['store_id']]."','".$value['goods_id']."','".$value['goods_name']."','".
			$value['spec_id']."','".$value['specification']."','".$value['price']."','".$value['quantity']."','".$value['goods_image']."')";
			if($endarr != $value){
				$inordergoods .= ',';
			}
        }
		$sqlfields = 'order_goods(order_id,goods_id,goods_name,spec_id,specification,price,quantity,goods_image)';
		$order_model->db->query('INSERT INTO '.DB_PREFIX.$sqlfields.' VALUES'.$inordergoods);
		//返回订单结果集合
		$actorderes['orderidarr'] =  $orderidarr;
		$actorderes['sumorderarr'] =  $sumorderarr;
		$actorderes['newordersn'] =  $sumordersn;
		$actorderes['sumorderid '] =  $sumorderid;
        return $actorderes;
    }
}

?>