<?php

/**
 *    收银台控制器，其扮演的是收银员的角色，你只需要将你的订单交给收银员，收银员按订单来收银，她专注于这个过程
 *
 *    @author    Garbin
 */
class CashierApp extends ShoppingbaseApp
{
    /**
     *    根据提供的订单信息进行支付
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function index()
    {
        /* 外部提供订单号 */
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (!$order_id)
        {
            $this->show_warning('no_such_order');

            return;
        }
        /* 内部根据订单号收银,获取收多少钱，使用哪个支付接口 */
        $order_model =& m('order');
        $order_info  = $order_model->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
        if (empty($order_info))
        {
            $this->show_warning('no_such_order');

            return;
        }
        /* 订单有效性判断 */
        if ($order_info['payment_code'] != 'cod' && $order_info['status'] != ORDER_PENDING)
        {
            $this->show_warning('no_such_order');
            return;
        }
        $payment_model =& m('payment');
        if (!$order_info['payment_id'])
        {
            /* 若还没有选择支付方式，则让其选择支付方式 */
            $payments = $payment_model->get_enabled($order_info['seller_id']);
            if (empty($payments))
            {
                $this->show_warning('store_no_payment');

                return;
            }
            /* 找出配送方式，判断是否可以使用货到付款 */
            $model_extm =& m('orderextm');
            $consignee_info = $model_extm->get($order_id);
            if (!empty($consignee_info))
            {
                /* 需要配送方式 */
                $model_shipping =& m('shipping');
                $shipping_info = $model_shipping->get($consignee_info['shipping_id']);
                $cod_regions   = unserialize($shipping_info['cod_regions']);
                $cod_usable = true;//默认可用
                if (is_array($cod_regions) && !empty($cod_regions))
                {
                    /* 取得支持货到付款地区的所有下级地区 */
                    $all_regions = array();
                    $model_region =& m('region');
                    foreach ($cod_regions as $region_id => $region_name)
                    {
                        $all_regions = array_merge($all_regions, $model_region->get_descendant($region_id));
                    }

                    /* 查看订单中指定的地区是否在可货到付款的地区列表中，如果不在，则不显示货到付款的付款方式 */
                    if (!in_array($consignee_info['region_id'], $all_regions))
                    {
                        $cod_usable = false;
                    }
                }
                else
                {
                    $cod_usable = false;
                }
                if (!$cod_usable)
                {
                    /* 从列表中去除货到付款的方式 */
                    foreach ($payments as $_id => $_info)
                    {
                        if ($_info['payment_code'] == 'cod')
                        {
                            /* 如果安装并启用了货到付款，则将其从可选列表中去除 */
                            unset($payments[$_id]);
                        }
                    }
                }
            }
            $all_payments = array('online' => array(), 'offline' => array());
            foreach ($payments as $key => $payment)
            {
                if ($payment['is_online'])
                {
                    $all_payments['online'][] = $payment;
                }
                else
                {
                    $all_payments['offline'][] = $payment;
                }
            }
            $this->assign('order', $order_info);
            $this->assign('payments', $all_payments);
            $this->_curlocal(
                LANG::get('cashier')
            );

            $this->_config_seo('title', Lang::get('confirm_payment') . ' - ' . Conf::get('site_title'));
            $this->display('cashier.payment.html');
        }
        else
        {
            /* 否则直接到网关支付 */
            /* 验证支付方式是否可用，若不在白名单中，则不允许使用 */
            if (!$payment_model->in_white_list($order_info['payment_code']))
            {
                $this->show_warning('payment_disabled_by_system');

                return;
            }

            $payment_info  = $payment_model->get("payment_code = '{$order_info['payment_code']}' AND store_id={$order_info['seller_id']}");
            /* 若卖家没有启用，则不允许使用 */
            if (!$payment_info['enabled'])
            {
                $this->show_warning('payment_disabled');

                return;
            }

            /* 生成支付URL或表单 */
            $payment    = $this->_get_payment($order_info['payment_code'], $payment_info);
            $payment_form = $payment->get_payform($order_info);

            /* 货到付款，则显示提示页面 */
            if ($payment_info['payment_code'] == 'cod')
            {
                $this->show_message('cod_order_notice',
                    'view_order',   'index.php?app=buyer_order',
                    'close_window', 'javascript:window.close();'
                );

                return;
            }

            /* 线下付款的 */
            if (!$payment_info['online'])
            {
                $this->_curlocal(
                    Lang::get('post_pay_message')
                );
            }

            /* 跳转到真实收银台 */
            $this->_config_seo('title', Lang::get('cashier'));
            $this->assign('payform', $payment_form);
            $this->assign('payment', $payment_info);
            $this->assign('order', $order_info);
            header('Content-Type:text/html;charset=' . CHARSET);
            $this->display('cashier.payform.html');
        }
    }

    /**
     *    确认支付
     *
     *    @author    Garbin
     *    @return    void
     */
    function goto_pay()
    {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        if (!$order_id)
        {
            $this->show_warning('no_such_order');

            return;
        }
        if (!$payment_id)
        {
            $this->show_warning('no_such_payment');

            return;
        }
        $order_model =& m('order');
        $order_info  = $order_model->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
        if (empty($order_info))
        {
            $this->show_warning('no_such_order');

            return;
        }

        #可能不合适
        if ($order_info['payment_id'])
        {
            $this->_goto_pay($order_id);
            return;
        }

        /* 验证支付方式 */
        $payment_model =& m('payment');
        $payment_info  = $payment_model->get($payment_id);
        if (!$payment_info)
        {
            $this->show_warning('no_such_payment');

            return;
        }

        /* 保存支付方式 */
        $edit_data = array(
            'payment_id'    =>  $payment_info['payment_id'],
            'payment_code'  =>  $payment_info['payment_code'],
            'payment_name'  =>  $payment_info['payment_name'],
        );

        /* 如果是货到付款，则改变订单状态 */
        if ($payment_info['payment_code'] == 'cod')
        {
            $edit_data['status']    =   ORDER_SUBMITTED;
        }

        $order_model->edit($order_id, $edit_data);

        /* 开始支付 */
        $this->_goto_pay($order_id);
    }

    /**
     *    线下支付消息
     *
     *    @author    Garbin
     *    @return    void
     */
    function offline_pay()
    {
        if (!IS_POST)
        {
            return;
        }
        $order_id       = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $pay_message    = isset($_POST['pay_message']) ? trim($_POST['pay_message']) : '';
        if (!$order_id)
        {
            $this->show_warning('no_such_order');
            return;
        }
        if (!$pay_message)
        {
            $this->show_warning('no_pay_message');

            return;
        }
        $order_model =& m('order');
        $order_info  = $order_model->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
        if (empty($order_info))
        {
            $this->show_warning('no_such_order');

            return;
        }
        $edit_data = array(
            'pay_message' => $pay_message
        );

        $order_model->edit($order_id, $edit_data);

        /* 线下支付完成并留下pay_message,发送给卖家付款完成提示邮件 */
        $model_member =& m('member');
        $seller_info   = $model_member->get($order_info['seller_id']);
        $mail = get_mail('toseller_offline_pay_notify', array('order' => $order_info, 'pay_message' => $pay_message));
        $this->_mailto($seller_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

        $this->show_message('pay_message_successed',
            'view_order',   'index.php?app=buyer_order',
            'close_window', 'javascript:window.close();');
    }

    function _goto_pay($order_id)
    {
        header('Location:index.php?app=cashier&order_id=' . $order_id);
    }
	
	/**
     *    确认支付
     *
     *    @author    newrain
     *    @return    void
     */
    function ejgoto_pay()
    {
        $order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
        $type = isset($_REQUEST['type']) ? intval($_REQUEST['type']) : 0;//判断是总单还是分单 0分单  1总单
        $payment_id = isset($_REQUEST['payment_id']) ? intval($_REQUEST['payment_id']) : '';//目前只支持微信支付
        if (!$order_id || !$payment_id)
        {
            return $this->ej_json_failed(2001);
        }
        //确定订单类型值
		if($type !=0 && $type != 1){
			return $this->ej_json_failed(2001);
		}
		$order_model =& m('order');
		if($type == 0){
			//单个订单支付详情
			$order_info  = $order_model->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
			//判断用户是否下过订单
			if (empty($order_info))
			{
				return $this->ej_json_failed(3001);
			}
			//限制订单待付款状态
			if($order_info['status'] != ORDER_PENDING){
				return $this->ej_json_failed(1012);
			}
			//订单去支付状态，48小时内未支付系统自动交易关闭   临界状态
			$overtime = time()-$order_info['add_time'];
			if($overtime >=172800){
				$this->_cancel_order($order_info['order_id'],'48小时内未支付系统自动交易关闭');
				return $this->ej_json_failed(1006);
			}
		}else{
			//合并订单详情
			$order_info =  $order_model->db->getRow("select id,orderid,ordersn from ".DB_PREFIX."sumorder where id=".intval($order_id)." and userid=".$this->visitor->get('user_id'));
			//判断用户是否下过订单
			if (empty($order_info))
			{
				return $this->ej_json_failed(3001);
			}
			//限制订单待付款状态
			$orderarr = json_decode($order_info['orderid'],true);
			$sumstatus  = $order_model->getOne("SELECT status from ".DB_PREFIX."order WHERE order_id=".key($orderarr));
			if($sumstatus != ORDER_PENDING){
				return $this->ej_json_failed(1012);
			}
		}
        /* 验证艺加支付方式 查看是否开启此支付*/
		$payment_info = $order_model->db->getRow("select payment_id,payment_code,payment_name from ".DB_PREFIX."ejpayment where payment_id=".intval($payment_id));
        if (!$payment_info)
        {
            return $this->ej_json_failed(3001);
        }

        /* 保存支付方式 */
		$edit_data = array(
			'payment_id'    =>  $payment_info['payment_id'],
			'payment_code'  =>  $payment_info['payment_code'],
			'payment_name'  =>  $payment_info['payment_name'],
		);
		if($type==0){
			//单个订单支付
			$order_model->edit($order_id, $edit_data);
		}else{
			//合单支付
			$this->_ej_editPayment($order_info['orderid'],$edit_data);
		}
        /* 开始支付  判断用户是否存在openid*/
		if(!$_SESSION['wx_openid']){
			return $this->ej_json_failed(3001);
		}
		//选择用户支付方式
		switch ($payment_info['payment_code'])
		{
			case 'wx':
				$this->_ej_wxpay($order_info,$type);
				break;
			default:
				return $this->ej_json_failed(3001);
		}
    }
	
	//合单支付  保存订单支付方式
	function _ej_editPayment($order_id,$edit_data){
		$orderarr = json_decode($order_id,true);
 		foreach($orderarr as $k=>$v){
			$sqlpid .= " WHEN $k THEN ".$edit_data['payment_id'];
			$sqlpname .= " WHEN  $k THEN '".$edit_data['payment_name']."'";
			$sqlpcode .= " WHEN $k THEN '".$edit_data['payment_code']."'";
		}
		//获取key值字符串
		$orderstr = implode(',', array_keys($orderarr));
		$sql = "UPDATE ".DB_PREFIX."order SET payment_id = CASE order_id ".$sqlpid." END ".
				",payment_name = CASE order_id ".$sqlpname." END ".
				",payment_code = CASE order_id ".$sqlpcode." END ";
		$sql .= "  WHERE order_id IN (".$orderstr.")";
		$order_model =& m('order');
		$order_model->db->query($sql);
	}
	//艺加微信支付
	function _ej_wxpay($order_info,$type=0){
		//print_r($order_info);exit;
		
		if($type==1){
			$order_model =& m('order');
			$orderarr = json_decode($order_info['orderid'],true);
			$idarr = array_keys($orderarr);//获取所有id
			$idstr = '('.implode(',',$idarr).')';
			//精确计算订单金额 为保证准确，重新计算
			$amountinfo =  $order_model->db->getAll("select order_amount from ".DB_PREFIX."order where order_id in $idstr");
			$amount = 0;
			if($amountinfo){
				foreach($amountinfo as $v){
					$amount = $amount+$v['order_amount'];
				}
			}
			$out_trade_on = $order_info['ordersn'];
		}else{
			$amount = $order_info['order_amount'];
			$out_trade_on = $order_info['order_sn'];
		}
		$attributes = [
			'trade_type'       => 'JSAPI', // JSAPI，NATIVE，APP...
			'body'             => '商城下单商品',
			'detail'           => '商城下单商品',
			'out_trade_no'     => $out_trade_on,
			'total_fee'        => $amount*100, // 单位分
			'notify_url'       => 'http://tst.yijiapai.com/api/wxpay.php', // 支付结果通知网址，如果不设置则会使用配置里的默认地址
			'openid'           => $_SESSION['wx_openid'], // trade_type=JSAPI，此参数必传，用户在商户appid下的唯一标识，
		];
		//调取微信支付底层代码
		$prepayarr = Wechat::pay($attributes);
		if($prepayarr){
			return $this->ej_json_success($prepayarr);
		}
		return $this->ej_json_failed(3001);
	}
	
		/**
         *    取消订单
         *
         * @author    Garbin
         * @return    void
         */
        function _cancel_order($orderid = 0,$remark='') {
            $order_id = isset( $_REQUEST['order_id'] ) ? intval($_REQUEST['order_id']) : $orderid;
            if ( !$order_id ) {
				return $this->ej_json_failed(2001);
            }
            $model_order =& m('order');
            /* 只有待付款的订单可以取消 */
            $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id') . " AND status " . db_create_in([ ORDER_PENDING, ORDER_SUBMITTED ]));
            if ( empty( $order_info ) ) {
				return $this->ej_json_failed(3001);
            }
			$model_order->edit($order_id, [ 'status' => ORDER_CANCELED,'cancel_time'=>time() ]);
			if ( $model_order->has_error() ) {
				return $this->ej_json_failed(3001);
			}
			/* 加回商品库存 */
			$model_order->change_stock('+', $order_id);
			$cancel_reason = $remark;
			/* 记录订单操作日志 */
			$order_log =& m('orderlog');
			$order_log->add([
				'order_id'       => $order_id,
				'operator'       => addslashes($this->visitor->get('user_name')),
				'order_status'   => order_status($order_info['status']),
				'changed_status' => order_status(ORDER_CANCELED),
				'remark'         => $cancel_reason,
				'log_time'       => gmtime(),
			]);
        }

}

?>
