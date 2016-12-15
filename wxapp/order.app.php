<?php
/**
 *    售货员控制器，其扮演实际交易中柜台售货员的角色，你可以这么理解她：你告诉我（售货员）要买什么东西，我会询问你你要的收货地址是什么之类的问题
 *        并根据你的回答来生成一张单子，这张单子就是“订单”
 *
 *    @author    newrain
 *    @param    none
 *    @return    void
 */
class OrderApp extends ShoppingbaseApp
{
	//结算页面接口
	function checkout(){
		$param = isset($_REQUEST['recid'])?stripslashes($_REQUEST['recid']):'';//recid为 ["13","15"]
		$checkoutinfo = $this->_ejget_ordercarts($param);//结算页面详情
		if($checkoutinfo === false){
			return $this->ej_json_failed(1010);
		}
		//检测所有购物车中的商品，完成库存判断 && 筛选用户所需要的总价格
		$goodslist = array();
		$totalamount = 0;
		$resultarr = array();
		foreach($checkoutinfo as $value){
			//是否失效
			if($value['if_show']!='1' || $value['closed'] == 1){
				return $this->ej_json_failed(1009);
				break;
			}
			$payshiprice = 0; //用户应付运费   针对商品不同运费不同  筛选价格最高运费
			foreach($value['goods'] as $v){
				if($v['shiprice']>=$payshiprice){
					$payshiprice = $v['shiprice'];
				}
				array_push($goodslist,$v);
			}
			$value['amount'] =  $value['amount'] + $payshiprice;//运费和商品价格总和
			$value['shiprice'] =  $payshiprice;
			array_push($resultarr,$value);
			//获取总金额
			$totalamount = $totalamount + $value['amount'];
		}
		//检测购物车商品库存是否充足
		$goods_beyond = $this->_check_beyond_stock($goodslist);
		if ($goods_beyond)
        {
            $str_tmp = '';
            foreach ($goods_beyond as $goods)
            {
                //$str_tmp .= $goods['goods_name'] . '库存只剩' . $goods['stock'].'件啦，';
                $str_tmp .= $goods['goods_name'] . ',';
            }
			$strstock = "您购买的".$str_tmp."目前库存不足，系统已为您自动配置！";
			return $this->ej_json_failed(1005,$strstock);
        }
		//拼装结果集
		$result['list'] = $resultarr;
		$result['totalamount'] = $totalamount;
		return $this->ej_json_success($result);
	}
	
	
	/**
	*	用户下单接口    与之前版本不同  重新编写
	*    @author    newrain
    *    @param    none
    *    @return    void
	*/
	function actorder(){
		$param = isset($_REQUEST['recid'])?stripslashes($_REQUEST['recid']):'';//recid为 ["13","15"]
		$goods_info = $this->_ejget_ordercarts($param);//下单购物车接口
		if($goods_info === false){
			return $this->ej_json_failed(1010);
		}
		//检测所有购物车中的商品，完成库存判断 && 筛选用户所需要的总价格
		$goodslist = array();
		$totalamount = 0;
		$resultarr = array();
		foreach($goods_info as $value){
			//是否失效
			if($value['if_show']!='1' || $value['closed'] == 1){
				return $this->ej_json_failed(1009);
				break;
			}
			$payshiprice = 0; //用户应付运费   针对商品不同运费不同  筛选价格最高运费
			foreach($value['goods'] as $v){
				if($v['shiprice']>=$payshiprice){
					$payshiprice = $v['shiprice'];
				}
				array_push($goodslist,$v);
			}
			$value['amount'] =  $value['amount'] + $payshiprice;//运费和商品价格总和
			$value['shiprice'] =  $payshiprice;//改变原有思路，每件商品都有对应的运费，选择最大运费
			array_push($resultarr,$value);
			//获取总金额(总订单金额)
			$totalamount = $totalamount + $value['amount'];
		}
		//检测购物车商品库存是否充足
		$goods_beyond = $this->_check_beyond_stock($goodslist);
		if ($goods_beyond)
        {
            $str_tmp = '';
            foreach ($goods_beyond as $goods)
            {
                $str_tmp .= $goods['goods_name'] . '库存只剩' . $goods['stock'].'件啦，';
            }
			$strstock = "您购买的".$str_tmp."请修改数量再重新提交哦！";
			return $this->ej_json_failed(1005,$strstock);
        }
		/* 在此获取生成订单的两个基本要素：用户提交的数据（POST），商品信息（包含商品列表，商品总价，商品总数量，类型），所属店铺 */
		/* 优惠券数据处理   暂时不考虑团购  如2期添加  详情见index action中*/
		/* 根据商品类型获取对应的订单类型 */
		$goods_type =& gt('material');
		$order_type =& ot('normal');
		/* 将这些信息传递给订单类型处理类生成订单(你根据我提供的信息生成一张订单) */
		$orderidarr = $order_type->ejsubmit_order(array(
			'goods_info'    =>  $resultarr,      //商品信息（包括列表，总价，总量，所属店铺，类型）,可靠的!
			//'post'          =>  $_POST,           //用户填写的订单信息   目前用户post过来的数据为用户补充说明和收货地址相关   
			'post'          =>  $_REQUEST,           //用户填写的订单信息   目前用户post过来的数据为用户补充说明和收货地址相关   
		),$goodslist);
		if (!$orderidarr)
		{
			return $this->ej_json_failed(3001);
		}
		/*  检查是否添加收货人地址  微信登录用户收货地址直接从微信端获取不存储本地*/
		/* 下单完成后清理商品，如清空购物车，或将团购拍卖的状态转为已下单之类的 */
		$this->_ejclear_goods($param);
		/* 发送邮件 */
		$model_order =& m('order');
		/* 减去商品库存 */
		$model_order->ejchange_stock('-', $orderidarr['orderidarr']);
		/* 获取订单信息 */
		//$order_info = $model_order->get($order_id);
		/* 发送事件 详情查看action为index中操作 目前使用微信推送方式*/
		/* 发送给买家下单通知 */
		/* 发送给卖家新订单通知*/
		$model_goodsstatistics =& m('goodsstatistics');
		$goods_ids = array();
		foreach ($goodslist as $goods)
		{
			$goods_ids[] = $goods['goods_id'];
		}
		$model_goodsstatistics->edit($goods_ids, 'orders=orders+1');
		/* 到收银台付款 */
		$data['ordersn'] = $orderidarr['sumorderarr'];
		$data['pordersn'] = $orderidarr['newordersn'];
		$data['porderid'] = $orderidarr['sumorderid'];
		$data['totalamount'] = $totalamount;
		return $this->ej_json_success($data);
	}
    function _check_beyond_stock($goods_items)
    {
        $goods_beyond_stock = array();
		$model_cart =& m('cart');
        foreach ($goods_items as $rec_id => $goods)
        {
            if ($goods['quantity'] > $goods['stock'])
            {
                $goods_beyond_stock[$goods['spec_id']] = $goods;
				$where = "rec_id = ".$goods['rec_id'];
				/* 修改数量 */
				$model_cart->edit($where, [
					'quantity' => $goods['stock'],
				]);
            }
        }
        return $goods_beyond_stock;
    }
	
	/**
     *    以购物车为单位获取购物车列表及商品项
     *
     *    @author    newrain
     *    @return    void
     */
    function _ejget_ordercarts($param='')
    {
        $carts = array();
        /* 获取所有购物车中的内容 */
		if(!empty($param)){
			//处理所传参数
			$paramarr = json_decode($param,true);
			$param = implode(',',$paramarr);
			$where_cart_goods = ' AND cart.rec_id in ('.$param.')';
		}
        /* 只有是自己购物车的项目才能购买 */
        $where_user_id = $this->visitor->get('user_id') ? " AND cart.user_id=" . $this->visitor->get('user_id') : '';
        $cart_model =& m('cart');
		$sql =  "select cart.*,s.store_name,sp.stock,sp.shiprice,g.if_show,g.closed from ".DB_PREFIX."cart cart ".
				' LEFT JOIN '.DB_PREFIX.'store s on s.store_id = cart.store_id '.
				' LEFT JOIN '.DB_PREFIX.'goods_spec sp on sp.goods_id = cart.goods_id '.
				' LEFT JOIN '.DB_PREFIX.'goods g on g.goods_id = cart.goods_id '.
				' where 1=1 ' . $where_store_id . $where_user_id . $where_cart_goods;//添加sess_id  保证单点购物的唯一性
		$cart_items = $cart_model->getAll($sql);
        if (empty($cart_items))
        {
            return $carts;
        }
        $kinds = array();
        foreach ($cart_items as $item)
        {
            /* 小计 */
            $item['subtotal']   = $item['price'] * $item['quantity'];
            $kinds[$item['store_id']][$item['goods_id']] = 1;

            /* 以店铺ID为索引 */
            empty($item['goods_image']) && $item['goods_image'] = Conf::get('default_goods_image');
            $carts[$item['store_id']]['store_name'] = $item['store_name'];
            $carts[$item['store_id']]['store_id'] = $item['store_id'];
            $carts[$item['store_id']]['amount']     += $item['subtotal'];   //各店铺的总金额
            $carts[$item['store_id']]['quantity']   += $item['quantity'];   //各店铺的总数量
            $carts[$item['store_id']]['goods'][]    = $item;
        }

        foreach ($carts as $_store_id => $cart)
        {
			$carts[$_store_id]['type']   = 'material';
            $carts[$_store_id]['otype']    = 'normal';
            $carts[$_store_id]['kinds'] =   count(array_keys($kinds[$_store_id]));  //各店铺的商品种类数
        }
        return $carts;
    }
	/**
     *    下单完成后清理商品
     *
     *    @author    Garbin
     *    @return    void
     */
    function _ejclear_goods($param = '')
    {
		if($param){
			//处理所传参数
			$paramarr = json_decode($param,true);
			$param = implode(',',$paramarr);
			$where_cart_goods = ' AND rec_id in ('.$param.')';
		}else{
			$where_cart_goods = '';
		}
		/* 订单下完后清空指定购物车 */
		$where_user_id = $this->visitor->get('user_id') ? " user_id=" . $this->visitor->get('user_id') : '';
		$model_cart =& m('cart');
		//$model_cart->drop("store_id = {$store_id} AND session_id='" . SESS_ID . "'");
		$model_cart->drop($where_user_id.$where_cart_goods);
		//优惠券信息处理  v1.0未涉及到优惠券
    }
	
	//支付获取订单信息newrain
	function getpayorder(){
        $order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
        $type = isset($_REQUEST['type']) ? intval($_REQUEST['type']) : 0;//判断是总单还是分单 0分单 //目前只支持微信支付
        if (!$order_id)
        {
            return $this->ej_json_failed(2001);
        }
        //确定订单类型值
		if($type !=0 && $type != 1){
			return $this->ej_json_failed(2001);
		}
		$order_model =& m('order');
		$result = array();
		if($type == 0){
			//单个订单支付详情
			$order_info  = $order_model->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
			$result['ordersn'] = $order_info['order_sn'];
			$result['amount'] = $order_info['order_amount'];
		}else{
			//合并订单详情
			$order_info =  $order_model->db->getRow("select id,orderid,ordersn from ".DB_PREFIX."sumorder where id=".intval($order_id)." and userid=".$this->visitor->get('user_id'));
			//获取总金额
			$orderarr = json_decode($order_info['orderid'],true);
			$orderstr = implode(',', array_keys($orderarr));
			$amount =  $order_model->db->getOne("select sum(order_amount) as sumamount from ".DB_PREFIX."order where order_id in (".$orderstr.')');
			$result['ordersn'] = $order_info['ordersn'];
			$result['amount'] = $amount;
		}
		if(!$result){
			return $this->ej_json_failed(3001);
		}
		return $this->ej_json_success($result);
	}
}