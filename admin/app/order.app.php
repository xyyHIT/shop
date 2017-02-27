<?php

/**
 *    合作伙伴控制器
 *
 *    @author    Garbin
 *    @usage    none
 */
class OrderApp extends BackendApp
{
    /**
     *    管理
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function index()
    {
        $search_options = array(
            'seller_name'   => Lang::get('store_name'),
            'buyer_name'   => Lang::get('buyer_name'),
            'payment_name'   => Lang::get('payment_name'),
            'order_sn'   => Lang::get('order_sn'),
        );
        /* 默认搜索的字段是店铺名 */
        $field = 'seller_name';
        array_key_exists($_GET['field'], $search_options) && $field = $_GET['field'];
        $conditions = $this->_get_query_conditions(array(array(
                'field' => $field,       //按用户名,店铺名,支付方式名称进行搜索
                'equal' => 'LIKE',
                'name'  => 'search_name',
            ),array(
                'field' => 'status',
                'equal' => '=',
                'type'  => 'numeric',
            ),array(
                'field' => 'add_time',
                'name'  => 'add_time_from',
                'equal' => '>=',
                'handler'=> 'gmstr2time',
            ),array(
                'field' => 'add_time',
                'name'  => 'add_time_to',
                'equal' => '<=',
                'handler'   => 'gmstr2time_end',
            ),array(
                'field' => 'order_amount',
                'name'  => 'order_amount_from',
                'equal' => '>=',
                'type'  => 'numeric',
            ),array(
                'field' => 'order_amount',
                'name'  => 'order_amount_to',
                'equal' => '<=',
                'type'  => 'numeric',
            ),
        ));
        $model_order =& m('order');
        $page   =   $this->_get_page(10);    //获取分页信息
        //更新排序
        if (isset($_GET['sort']) && isset($_GET['order']))
        {
            $sort  = strtolower(trim($_GET['sort']));
            $order = strtolower(trim($_GET['order']));
            if (!in_array($order,array('asc','desc')))
            {
             $sort  = 'add_time';
             $order = 'desc';
            }
        }
        else
        {
            $sort  = 'add_time';
            $order = 'desc';
        }
        $orders = $model_order->find(array(
            'conditions'    => '1=1 ' . $conditions,
            'limit'         => $page['limit'],  //获取当前页的数据
            'order'         => "$sort $order",
            'count'         => true             //允许统计
        )); //找出所有商城的合作伙伴
		//更改add_time时间显示
		if($orders){
			foreach($orders as $key=>$value){
				$orders[$key]['add_time'] = empty($value['add_time'])?'':date('Y-m-d H:i:s',$value['add_time']);
			}
		}
        $page['item_count'] = $model_order->getCount();   //获取统计的数据
        $this->_format_page($page);
        $this->assign('filtered', $conditions? 1 : 0); //是否有查询条件
        $this->assign('order_status_list', array(
            ORDER_PENDING => Lang::get('order_pending'),
            ORDER_SUBMITTED => Lang::get('order_submitted'),
            ORDER_ACCEPTED => Lang::get('order_accepted'),
            ORDER_SHIPPED => Lang::get('order_shipped'),
            ORDER_FINISHED => Lang::get('order_finished'),
            ORDER_CANCELED => Lang::get('order_canceled'),
        ));
        $this->assign('search_options', $search_options);
        $this->assign('page_info', $page);          //将分页信息传递给视图，用于形成分页条
        $this->assign('orders', $orders);
        $this->import_resource(array('script' => 'inline_edit.js,jquery.ui/jquery.ui.js,jquery.ui/i18n/' . i18n_code() . '.js',
                                      'style'=> 'jquery.ui/themes/ui-lightness/jquery.ui.css'));
        $this->display('order.index.html');
    }

    /**
     *    查看
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function view()
    {
        $order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$order_id)
        {
            $this->show_warning('no_such_order');

            return;
        }

        /* 获取订单信息 */
        $model_order =& m('order');
        $order_info = $model_order->get(array(
            'conditions'    => $order_id,
            'join'          => 'has_orderextm',
            'include'       => array(
                'has_ordergoods',   //取出订单商品
            ),
        ));

        if (!$order_info)
        {
            $this->show_warning('no_such_order');
            return;
        }
        $order_type =& ot($order_info['extension']);
        $order_detail = $order_type->get_order_detail($order_id, $order_info);
        $order_info['group_id'] = 0;
        if ($order_info['extension'] == 'groupbuy')
        {
            $groupbuy_mod =& m('groupbuy');
            $groupbuy = $groupbuy_mod->get(array(
                'fields' => 'groupbuy.group_id',
                'join' => 'be_join',
                'conditions' => "order_id = {$order_info['order_id']} ",
                )
            );
            $order_info['group_id'] = $groupbuy['group_id'];
        }
        foreach ($order_detail['data']['goods_list'] as $key => $goods)
        {
            if (substr($goods['goods_image'], 0, 7) != 'http://')
            {
                $order_detail['data']['goods_list'][$key]['goods_image'] = SITE_URL . '/' . $goods['goods_image'];
            }
        }
		$order_info['add_time'] = date('Y-m-d H:i:s',$order_info['add_time']);
		$order_info['pay_time'] = empty($order_info['pay_time'])?'':date('Y-m-d H:i:s',$order_info['pay_time']);
		$order_info['ship_time'] = empty($order_info['ship_time'])?'':date('Y-m-d H:i:s',$order_info['ship_time']);
		$order_info['finished_time'] = empty($order_info['finished_time'])?'':date('Y-m-d H:i:s',$order_info['finished_time']);
        $this->assign('order', $order_info);
        $this->assign($order_detail['data']);
        $this->display('order.view.html');
    }
	
	//查看物流单号
	function view_invoice()
	{	
		$invoice_no = isset($_GET['no']) ? intval($_GET['no']) : 0;
		if (!$invoice_no)
		{
			die('no power');
		}
		//获取货物单号信息
 		require ROOT_PATH.'/includes/Http.php';
		$http = new Http();
		$url ='http://highapi.kuaidi.com/openapi-querycountordernumber.html';
		$jsonArr = $http->parseJSON($http->get($url, [
			'id' => 'c56a2caaa564bf6803b978fb58393087',
			'nu' => $invoice_no
		]));
		$this->assign('data', $jsonArr['data']);
		$this->display('order.invoice.html');
	}
	
	//冻结订单
	function edit_frozen(){
		$id = empty($_GET['id']) ? 0 : intval($_GET['id']);
        if (!IS_POST)
        {
			/* 获取订单信息 */
			$model_order =& m('order');
			$order_info = $model_order->get(array(
				'conditions'    => $id
			));
			$this->assign('data', $order_info);
            $this->display('frozen.form.html');
        }
        else
        {
			$fronzen_reson = empty($_POST['frozen_reason'])?'':trim($_POST['frozen_reason']);
			$orderid = empty($_POST['orderid'])?'':trim($_POST['orderid']);
            /* 检查名称是否已存在 */
			if(!$fronzen_reson||!$orderid){
				$this->show_warning('参数错误');
				return;
			}
			$model_order =& m('order');
			$order_info = $model_order->get(array(
				'conditions'    => $orderid
			));
			if(empty($order_info)){
				$this->show_warning('该订单无效！');
				return;	
			}
			//待付款和已完成的单无法冻结
 			if($order_info['status'] <= ORDER_PENDING || $order_info['status'] == ORDER_FINISHED ){
				$this->show_warning('该状态无法冻结！');
				return;	
			}
			/* 记录订单操作日志 */
			$order_log =& m('orderlog');
			$order_log->add([
				'order_id'       => $orderid,
				'operator'       => addslashes($this->visitor->get('user_name')),
				'order_status'   => order_status($order_info['status']),
				'changed_status' => '已冻结',
				'remark'         => $fronzen_reson,
				'log_time'       => time(),
			]);
			$model_order->edit($orderid, [ 'if_fronzen' => 1 ]);
            $this->show_message('冻结成功!',
                '订单列表',    'index.php?app=order&act=index'
            );
        }
	}
	
	//退款给卖家
	function give_seller_frozen(){
		$orderid = empty($_GET['id'])?'':trim($_GET['id']);
		/* 检查名称是否已存在 */
		if(!$orderid){
			$this->show_warning('参数错误');
			return;
		}
		$model_order =& m('order');
		$order_info = $model_order->get(array(
			'conditions'    => $orderid
		));
		if(empty($order_info)){
			$this->show_warning('该订单无效！');
			return;	
		}
		if($order_info['if_fronzen'] != 1){
			$this->show_warning('该订单无法分配！');
			return;	
		}
		$model_order->edit($orderid, [ 'status' => ORDER_FINISHED, 'finished_time' => gmtime() ]);
		if ( $model_order->has_error() ) {
			return $this->ej_json_failed(3001);
		}
		/* 更新累计销售件数 */
		$model_goodsstatistics =& m('goodsstatistics');
		$model_ordergoods =& m('ordergoods');
		$order_goods = $model_ordergoods->find("order_id={$orderid}");
		foreach ( $order_goods as $goods ) {
			$model_goodsstatistics->edit($goods['goods_id'], "sales=sales+{$goods['quantity']}");
			$sales_total = $goods['quantity']*$goods['price'];
			$model_goodsstatistics->edit($goods['goods_id'], "sales_total=sales_total+{$sales_total}");
		}
		//获取卖家的openid，记录流水表
		$userModel =& m('member');
		$sellArr = $userModel->get([
			'conditions' => "user_id='".$order_info['seller_id']."'",
			'fields' => 'openid',
		]);
		/* 记录订单操作日志 */
		$order_log =& m('orderlog');
		$order_log->add([
			'order_id'       => $orderid,
			'operator'       => addslashes($this->visitor->get('user_name')),
			'order_status'   => order_status($order_info['status']),
			'changed_status' => '已分派',
			'remark'         => '冻结订单分配给卖家',
			'log_time'       => time(),
		]);
		//将订单冻结状态改为已分派
		$model_order->edit($orderid, [ 'if_fronzen' => 2 ]);
		//像流水表更新
		$model_order->db->query("UPDATE ".DB_PREFIX."order_stream SET sopen_id='".$sellArr['openid']."' WHERE order_id=$orderid");
		//查出流水向拍卖对接
		$sreamarr = $model_order->db->getRow("SELECT tran_id,bopen_id,sopen_id,trade_amount,order_sn,pay_type FROM ".DB_PREFIX."order_stream WHERE order_id=$orderid");
		$data['tran_id'] = $sreamarr['tran_id'];
		$data['open_id'] = $sreamarr['sopen_id'];
		$data['buyer'] = $sreamarr['bopen_id'];
		$data['trade_amount'] = $sreamarr['trade_amount'];
		$data['pay_type'] = $sreamarr['pay_type'];
		$data['order_sn'] = $sreamarr['order_sn'];
		$data['title'] = '订单支付';
		$data['trade_type'] = 28;
		$data['order_id'] = $orderid;
		include ROOT_PATH.'/includes/aes.base.php';
		$serialjson = Security::encrypt(json_encode($data),'yijiawang.com#@!');
		$this->_confirmcurl(array('data'=>$serialjson));
		//推送卖家确认收货消息
		$datas = [
			'first'=>'报告主人，订单'.$sreamarr['order_sn'].'买家已确认收货，小艺把钱已装入您的钱包,请注意查收哦！',
			'keyword1'=>$sreamarr['order_sn'],
			'keyword2'=>($sreamarr['trade_amount']/100)."元",
			'keyword3'=>date('Y-m-d H:i'),
			'remark'=>'请点击"详情"查看更多，如有任何疑问请联系我们。',
		];
		//获取相关提醒信息  进行提醒
		$result = Wechat::sendNotice($sellArr['openid'],CONFIRM_SELLER,$datas,SITE_URL."/shop/html/order/orderDetail.html?orderId=".$orderid."&type=1");
		$this->show_message('分派成功！',
			'订单列表',    'index.php?app=order&act=index',
			'订单详情',   'index.php?app=order&amp;act=view&amp;id=' . $orderid
		);
	}
	function give_buyer_forzen(){
		$orderid = empty($_GET['id'])?'':trim($_GET['id']);
		/* 检查名称是否已存在 */
		if(!$orderid){
			$this->show_warning('参数错误');
			return;
		}
		$model_order =& m('order');
		$order_info = $model_order->get(array(
			'conditions'    => $orderid
		));
		if(empty($order_info)){
			$this->show_warning('该订单无效！');
			return;	
		}
		if($order_info['if_fronzen'] != 1){
			$this->show_warning('该订单无法分配！');
			return;	
		}
		//判断用户是否支付
		$sreamarr = $model_order->db->getRow("SELECT tran_id,pay_type FROM ".DB_PREFIX."order_stream WHERE order_id=".$orderid);
		if(empty($sreamarr)){
			$this->show_warning('该订单未完成支付！');
			return;
		}
		switch ($sreamarr['pay_type'])
		{
			case 1://微信支付退款
			  $this->_wxpay_reduce($order_info);
			  break;
			case 0://余额支付退款
			  $this->_balpay_reduce($order_info);
			  break;
			default:
			  $this->show_warning('该订单无效！');
		}
	}
	//微信支付退款
	function _wxpay_reduce($order_info){
		//判断用户是否为微信支付
		$model_order =& m('order');
		//查出流水向拍卖对接
		$sreamarr = $model_order->db->getRow("SELECT tran_id,trade_amount,order_id,order_sn FROM ".DB_PREFIX."order_stream WHERE order_id=".$order_info['order_id']);
		$count_orderid = strlen($sreamarr['order_id']);
		if(substr($sreamarr['tran_id'],-$count_orderid) == $sreamarr['order_id']){
			$tran_id = substr($sreamarr['tran_id'],0,-$count_orderid);
		}else{
			$tran_id = $sreamarr['tran_id'];
		}
		$sum_amount = $model_order->db->getOne("SELECT sum(trade_amount) FROM ".DB_PREFIX."order_stream WHERE tran_id like '".$tran_id."%'");
		$attributes['sum_amount'] = $sum_amount;
		$attributes['trade_amount'] = $sreamarr['trade_amount'];
		$attributes['refundNo'] = '3'.$sreamarr['order_sn'];
		$attributes['tran_id'] = str_replace('fin','',$tran_id);
		$result = Wechat::reduce($attributes);
		if(!$result){
			$this->show_warning('微信退款失败！');
			return;
		}
		/* 加回商品库存 */
		$model_order->edit($order_info['order_id'], [ 'status' => ORDER_CANCELED,'cancel_time'=>time() ]);
		$model_order->change_stock('+', $order_info['order_id']);
		/*将冻结编辑为已分派*/
		$model_order->edit($order_info['order_id'], [ 'if_fronzen' => 2 ]);
		//获取卖家的openid，记录流水表
		$userModel =& m('member');
		$sellArr = $userModel->get([
			'conditions' => "user_id='".$order_info['seller_id']."'",
			'fields' => 'openid',
		]);
		//像流水表更新
		$model_order->db->query("UPDATE ".DB_PREFIX."order_stream SET sopen_id='".$sellArr['openid']."' WHERE order_id=".$order_info['order_id']);
		//查出流水向拍卖对接
		$sreamarr = $model_order->db->getRow("SELECT tran_id,bopen_id,sopen_id,trade_amount,order_sn,pay_type FROM ".DB_PREFIX."order_stream WHERE order_id=".$order_info['order_id']);
		$data['tran_id'] = $sreamarr['tran_id'];
		$data['open_id'] = $sreamarr['bopen_id'];//买家openid
		$data['trade_amount'] = $sreamarr['trade_amount'];
		$data['pay_type'] = $sreamarr['pay_type'];
		$data['order_sn'] = $sreamarr['order_sn'];
		$data['title'] = '订单退款';
		$data['trade_type'] = 24;
		$data['order_id'] = $order_info['order_id'];
		include ROOT_PATH.'/includes/aes.base.php';
		$serialjson = Security::encrypt(json_encode($data),'yijiawang.com#@!');
		$this->_confirmcurl(array('data'=>$serialjson));
		//推送买家退款消息
		$datas = [
			'first'=>'您有一笔冻结的订单，经平台判定，小艺已经把钱给您原路送回去了，请注意查收哦！',
			'reason'=>'平台已处理',
			'refund'=>($sreamarr['trade_amount']/100)."元",
			'remark'=>'如未收到请联系艺加拍卖客服 400-630-4180 ',
		];
		//获取相关提醒信息  进行提醒
		$result = Wechat::sendNotice($sreamarr['bopen_id'],REDUCE_BUYER,$datas,SITE_URL."/shop/html/order/orderDetail.html?orderId=".$order_info['order_id']."&type=0");
		$this->show_message('分派成功！',
			'订单列表',    'index.php?app=order&act=index',
			'订单详情',   'index.php?app=order&amp;act=view&amp;id=' . $order_info['order_id']
		);
	}
	//余额支付退款
	function _balpay_reduce($order_info){
		//获取卖家的openid，记录流水表
		$userModel =& m('member');
		$sellArr = $userModel->get([
			'conditions' => "user_id='".$order_info['seller_id']."'",
			'fields' => 'openid',
		]);
		/* 记录订单操作日志 */
		$order_log =& m('orderlog');
		$order_log->add([
			'order_id'       => $order_info['order_id'],
			'operator'       => addslashes($this->visitor->get('user_name')),
			'order_status'   => order_status($order_info['status']),
			'changed_status' => '已分派',
			'remark'         => '冻结订单分配给买家',
			'log_time'       => time(),
		]);
		$model_order =& m('order');
		/* 加回商品库存 */
		$model_order->edit($order_info['order_id'], [ 'status' => ORDER_CANCELED,'cancel_time'=>time() ]);
		$model_order->change_stock('+', $order_info['order_id']);
		/*将冻结编辑为已分派*/
		$model_order->edit($order_info['order_id'], [ 'if_fronzen' => 2 ]);
		//像流水表更新
		$model_order->db->query("UPDATE ".DB_PREFIX."order_stream SET sopen_id='".$sellArr['openid']."' WHERE order_id=".$order_info['order_id']);
		//查出流水向拍卖对接
		$sreamarr = $model_order->db->getRow("SELECT tran_id,bopen_id,sopen_id,trade_amount,order_sn,pay_type FROM ".DB_PREFIX."order_stream WHERE order_id=".$order_info['order_id']);
		$data['tran_id'] = $sreamarr['tran_id'];
		$data['open_id'] = $sreamarr['bopen_id'];//买家openid
		$data['trade_amount'] = $sreamarr['trade_amount'];
		$data['pay_type'] = $sreamarr['pay_type'];
		$data['order_sn'] = $sreamarr['order_sn'];
		$data['title'] = '订单退款';
		$data['trade_type'] = 24;
		$data['order_id'] = $order_info['order_id'];
		include ROOT_PATH.'/includes/aes.base.php';
		$serialjson = Security::encrypt(json_encode($data),'yijiawang.com#@!');
		$this->_confirmcurl(array('data'=>$serialjson));
		//推送买家退款消息
		$datas = [
			'first'=>'您有一笔冻结的订单，经平台判定，小艺已经把钱给您原路送回去了，请注意查收哦！',
			'reason'=>'平台已处理',
			'refund'=>($sreamarr['trade_amount']/100)."元",
			'remark'=>'如未收到请联系艺加拍卖客服 400-630-4180 ',
		];
		//获取相关提醒信息  进行提醒
		$result = Wechat::sendNotice($sreamarr['bopen_id'],REDUCE_BUYER,$datas,SITE_URL."/shop/html/order/orderDetail.html?orderId=".$order_info['order_id']."&type=0");
		$this->show_message('分派成功！',
			'订单列表',    'index.php?app=order&act=index',
			'订单详情',   'index.php?app=order&amp;act=view&amp;id=' . $order_info['order_id']
		);
	}
	function _confirmcurl($curlPost){
		//初始化
		$ch = curl_init("http://tst.yijiapai.com/yjpai/common/tools/addAccountCheck");
		//设置
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);    //设施post方式提交数据
		curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);    //设置POST的数据
		//执行会话并获取内容
		$output = curl_exec($ch);
		curl_close($ch);
		return json_decode($output,true);
	}
}
?>
