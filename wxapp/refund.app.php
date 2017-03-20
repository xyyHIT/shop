<?php
/*退款订单相关*/
class refundApp extends MemberbaseApp
{
	//申请退款页面，获取相关信息
	function applyRefund(){
		$order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
        $rec_id = isset($_REQUEST['rec_id']) ? intval($_REQUEST['rec_id']) : 0;
		if(!$order_id || !$rec_id){
			return $this->ej_json_failed(2001);
		}
		//判断该订单是否可退款
		$model_order =& m('order');
        $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
		if($order_info['status'] == 20 && $order_info['status'] = 30){
			return $this->ej_json_failed(3001);
		}
		$order_type =& ot($order_info['extension']);
        $order_detail = $order_type->get_order_detail($order_id, $order_info);
		//获取指定商品退款价格(如果为最后一个订单申请则退款金额为商品价格加运费)
		$refund_price = $order_detail['data']['goods_list'][$rec_id]['price'] * $order_detail['data']['goods_list'][$rec_id]['quantity'] ;
		$is_refund = '';
		foreach($order_detail['data']['goods_list'] as $value){
			if($value['is_refund'] == 0 && $value['rec_id'] != $rec_id){
				$is_refund .= $value['rec_id'];
			}
		}
		if(!$is_refund){
			$refund_price = $refund_price + $order_detail['data']['order_extm']['shipping_fee'];
		}
		$res['order_id'] = $order_id;
		$res['rec_id'] = $rec_id;
		$res['refund_price'] = $refund_price;
		return $this->ej_json_success($res);
	}
	//申请退款动作
	function act_applyRefund(){
		$order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
        $rec_id = isset($_REQUEST['rec_id']) ? intval($_REQUEST['rec_id']) : 0;
        $reason = isset($_REQUEST['reason']) ? trim($_REQUEST['reason']) : '';
        $postscript  = isset($_REQUEST['postscript']) ? trim($_REQUEST['postscript']) : '';
		if(!$order_id || !$rec_id || !$reason){
			return $this->ej_json_failed(2001);
		}
		//判断订单是否可以退款
		$model_order =& m('order');
        $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
		if($order_info['status'] == 20 && $order_info['status'] = 30){
			return $this->ej_json_failed(3001);
		}
		//获取订单详细信息
		$order_type =& ot($order_info['extension']);
        $order_detail = $order_type->get_order_detail($order_id, $order_info);
		//获取指定商品退款价格(如果为最后一个订单申请则退款金额为商品价格加运费)
		$refund_price = $order_detail['data']['goods_list'][$rec_id]['price'] * $order_detail['data']['goods_list'][$rec_id]['quantity'] ;
		$is_refund = '';
		foreach($order_detail['data']['goods_list'] as $value){
			if($value['is_refund'] == 0 && $value['rec_id'] != $rec_id){
				$is_refund .= $value['rec_id'];
			}
		}
		if(!$is_refund){
			$refund_price = $refund_price + $order_detail['data']['order_extm']['shipping_fee'];
		}
		//存入退款订单表
		$sqlfields = 'order_refund(refund_sn,order_id,rec_id,goods_id,seller_id,seller_name,buyer_id,buyer_name,status,add_time,reason,postscript,amount,step)';
		$insersql = "('3".$order_info['order_sn']."',".$order_info['order_id'].",".$rec_id.",".$order_detail['data']['goods_list'][$rec_id]['goods_id'].",".$order_info['seller_id'].",'".$order_info['seller_name']."',".$order_info['buyer_id'].",'".$order_info['buyer_name']."',".REFUND_APPLY.",".time().",'".$reason."','".$postscript."',".$refund_price.",1)";
		$model_order->db->query('INSERT INTO '.DB_PREFIX.$sqlfields.' VALUES'.$insersql);
		$refund_id = $model_order->db->insert_id();
		//更改商品退款状态
		$model_ordergoods =& m('ordergoods');
		$editgoods = ['is_refund'=>1];
		$model_ordergoods->edit("rec_id={$rec_id} AND order_id={$order_id}", $editgoods);
		//记录日志
		$sqlfields = 'refund_log(refund_id,operator,refund_status,remark,log_time,refund_amount)';
		$insersql = "(".$refund_id.",'".$order_info['buyer_name']."',".REFUND_APPLY.",'买家申请退款',".time().",".$refund_price.")";
		$model_order->db->query('INSERT INTO '.DB_PREFIX.$sqlfields.' VALUES'.$insersql);
		$typearr = ['20'=>0,'30'=>1];//判断该订单为退款还是退款退货
		$res['type'] = $typearr[$order_info['status']];
		$res['refund_id'] = $refund_id;
		return $this->ej_json_success($res);
	}
	//买家退款订单列表
	function buyer_refundlist(){
		$status = isset($_REQUEST['status']) ? intval($_REQUEST['status']) : 0;
		$page = $this->_get_page(10);
		$model_order =& m('order');
		$where = '';
		if($status){
			$where .= ' AND r.status != 0 AND r.status !=5 ';
		}
		//获取买家订单
		$sql = "select r.id,r.seller_name,g.goods_name,g.goods_image,g.quantity,r.status as rstatus,o.status as ostatus,g.price,r.amount from ".DB_PREFIX."order_refund as r left join ".DB_PREFIX."order_goods as g on r.rec_id = g.rec_id left join ".DB_PREFIX."order as o on r.order_id = o.order_id where r.buyer_id = ".$this->visitor->get('user_id').$where." limit ".$page['limit'];
		$countsql = "select count(*) from ".DB_PREFIX."order_refund as r where r.buyer_id = ".$this->visitor->get('user_id').$where;
		$page['item_count'] = $model_order->getOne($countsql);
		$listarr = $model_order->getAll($sql);
		//按照买家退款订单情况处理订单
		$res = array();
		if($listarr){
			foreach($listarr as $value){
					switch ($value['rstatus']) {
						case REFUND_APPLY:
							$value['button'] = "<div class='dOperate'><a class='quxiaotuikuan'>取消退款</a></div>";
							$value['status_value'] = '退款中';
							break;
						case REFUND_REFUSE:
							$value['button'] = "<div class='dOperate'><a class='chongxinshenqing'>重新申请</a></div>";
							$value['status_value'] = '卖家拒绝退款';
							break;
						case REFUND_ACCESS:
							$value['button'] = '';
							$value['status_value'] = '退款中';
							if($value['ostatus'] == ORDER_SHIPPED){
								$value['button'] = "<div class='dOperate'><a class='qufahuo'>去发货</a></div>";
								$value['status_value'] = '等待买家处理';
							}
							break;
						case ORDER_SHIPPED:
							$value['button'] = "";
							$value['status_value'] = '等待卖家退款';
							break;
						case ORDER_FINISHED:
							$value['button'] = "<div class='dOperate'><a class='qiandequxiang'>钱的去向</a></div>";
							$value['status_value'] = "退款完成";
							break;
						case ORDER_CANCEL:
							$value['button'] = "";
							$value['status_value'] = '退款关闭';
							break;
						default:
							$value['button'] = '';
							$value['status_value'] = '';
					}
					array_push($res,$value);
			}
		}
		$data['list'] = $res;
		$data['page'] = $page;
		return $this->ej_json_success($data);
	}
	//卖家退款订单列表
	function seller_refundlist(){
		$status = isset($_REQUEST['status']) ? intval($_REQUEST['status']) : 0;
		$page = $this->_get_page(10);
		$model_order =& m('order');
		$where = '';
		if($status){
			$where .= ' AND r.status != 0 AND r.status !=5 ';
		}
		//获取卖家订单
		$sql = "select r.id,r.seller_name,g.goods_name,g.goods_image,g.quantity,r.status as rstatus,o.status as ostatus,g.price,r.amount from ".DB_PREFIX."order_refund as r left join ".DB_PREFIX."order_goods as g on r.rec_id = g.rec_id left join ".DB_PREFIX."order as o on r.order_id = o.order_id where r.seller_id = ".$this->visitor->get('user_id').$where." limit ".$page['limit'];
		$countsql = "select count(*) from ".DB_PREFIX."order_refund as r where r.seller_id = ".$this->visitor->get('user_id').$where;
		$page['item_count'] = $model_order->getOne($countsql);
		$listarr = $model_order->getAll($sql);
		//按照卖家退款订单情况处理订单
		$res = array();
		if($listarr){
			foreach($listarr as $value){
					switch ($value['rstatus']) {
						case REFUND_APPLY:
							$value['button'] = "<div class='dOperate'><a class='tongyishenqing'>同意申请</a><a class='jujueshenqing'>拒绝申请</a></div>";
							$value['status_value'] = '退款中';
							break;
						case REFUND_REFUSE:
							$value['button'] = '';
							$value['status_value'] = '卖家拒绝退款';
							break;
						case REFUND_ACCESS:
							$value['button'] = "<div class='dOperate'><a class='tongyituikuan'>同意退款</a><a class='jujuetuikuan'>拒绝退款</a></div>";
							$value['status_value'] = '退款中';
							if($value['ostatus'] == ORDER_SHIPPED){
								$value['button'] = '';
								$value['status_value'] = '等待买家退货';
							}
							break;
						case ORDER_SHIPPED:
							$value['button'] = "<div class='dOperate'><a class='tongyituikuan'>同意退款</a><a class='jujuetuikuan'>拒绝退款</a></div>";
							$value['status_value'] = '退款中';
							break;
						case ORDER_FINISHED:
							$value['button'] = "<div class='dOperate'><a class='qiandequxiang'>钱的去向</a></div>";
							$value['status_value'] = "退款完成";
							break;
						case ORDER_CANCEL:
							$value['button'] = "";
							$value['status_value'] = '退款关闭';
							break;
						default:
							$value['button'] = '';
							$value['status_value'] = '';
					}
					array_push($res,$value);
			}
		}
		$data['list'] = $res;
		$data['page'] = $page;
		return $this->ej_json_success($data);
	}
	//买家退款订单详情
	function buyer_view(){
		$refund_id = isset($_REQUEST['refund_id']) ? intval($_REQUEST['refund_id']) : 0;
		if(!$refund_id){
			return $this->ej_json_failed(2001);
		}
		//获取退款信息
		$sql = "select id,o.status as type,refund_sn,r.status,r.add_time,r.step,r.reason,r.postscript,r.invoice_address,r.amount from ".DB_PREFIX."order_refund as r left join ".DB_PREFIX."order as o on r.order_id = o.order_id where r.id = ".$refund_id;
		$model_order =& m('order');
		$detail = $model_order->getRow($sql);
		switch ($detail['status']) {
				case REFUND_APPLY:
					$detail['button'] = '<div class="refundContent">
					<div class="title"><span>等待卖家处理</span><span class="right"><em class="iconfont icon-bodadianhua"></em>联系卖家</span></div><div class="content">卖家在<span>'.date('Y-m-d H:i',$detail['add_time']).'</span>前未处理，系统将自动同意退款。</div><div class="buyerCall"><span class="quxiaotuikuan quxiaotuikuan">取消退款</span></div></div>';
					break;
				case REFUND_REFUSE:
					$detail['button'] = '<div class="refundContent"><div class="refundContent"><div class="title"><span>退款关闭</span><span class="right"><em class="iconfont icon-bodadianhua"></em></span></div><div class="content">买家未在7天内点击退货的，退款流程关闭，交易正常进行。</div></div><div class="call"><span class="agree agreeapply">重新申请</span><span><a href="tel:4006304108">小二介入</a></span></div></div>';
					if($detail['type']==30){
						$detail['button'] = '<div class="refundContent"><div class="refundContent"><div class="title"><span>卖家拒绝退款申请</span><span class="right"><em class="iconfont icon-bodadianhua"></em></span></div><div class="content">很抱歉，卖家拒绝了您的退款申请。如您在<span>6天23小时59分</span>申请退款，系统将自动取消退款申请。内未重新</div></div><div class="call"><span class="agree b-agreeapply">重新申请</span><span><a href="tel:4006304108">小二介入</a></span></div></div>';
					}
					break;
				case REFUND_ACCESS:
					$detail['button'] = "";
					if($detail['type']==30){
						$value['button'] = '<div class="refundContent">
						<div class="title"><span>等待买家退货</span><span class="right"><em class="iconfont icon-bodadianhua"></em>联系卖家</span></div>
						<div class="content">卖家同意后，请及时退货</div>
						<div class="content">退货地址：'.'北京市朝阳区惠河南街泰丰汇（艺加君 收） 13512345678'.'</div>
						<div class="content">如您在<span>6天23小时59分</span>内未处理，将自动取消退款申请。</div>
						<div class="buyerCall"><span class="quxiaotuikuan qutuihuo">去退货</span></div>
						</div>';
					}
					break;
				case ORDER_SHIPPED:
					$detail['button'] = '<div class="refundContent"><div class="title"><span>等待卖家退款</span><span class="right"><em class="iconfont icon-bodadianhua"></em>联系卖家</span></div><div class="content">卖家在<span>6天23小时59分</span>内未处理，系统将自动同意退款。</div></div>';
					break;
				case ORDER_FINISHED:
					$detail['button'] = '<div class="refundContent"><div class="title"><span>退款成功</span><span class="right"><em class="iconfont icon-bodadianhua"></em>联系卖家</span></div><div class="content">钱款已退回，请注意查收。</div><div class="buyerCall"><span class="quxiaotuikuan  qianquxiang">查看钱的去向</span></div></div>';
					break;
				case ORDER_CANCEL:
					$detail['button'] = '<div class="refundContent"><div class="title"><span>退款关闭</span><span class="right"><em class="iconfont icon-bodadianhua"></em>联系卖家</span></div><div class="content">由于您点击了取消退款，退款已关闭。您可重新点击退款申请。</div><div class="buyerCall"><span class="quxiaotuikuan">申请退款</span></div></div>';
					break;
				default:
					$detail['button'] = '';
			}
		//协商记录
		$logsql = "select refund_id,refund_status,remark,log_time from ".DB_PREFIX."refund_log where refund_id = ".$refund_id." order by log_time";
		$logarr = $model_order->getAll($logsql);
		$logresult = array();
		if($logarr){
			foreach($logarr as $value){
				$value['content'] = '';
				$value['log_time'] = date('Y-m-d H:i:s',$value['log_time']);
				switch ($value['refund_status']) {
					case REFUND_APPLY:
						$value['content'] = '<ul><li>退款原因：'.$detail['reason'].'</li><li>退款金额：'.$detail['amount'].'元</li><li>退款说明：'.$detail['postscript'].'</li></ul>';
						break;
					case REFUND_SHIP:
						$value['content'] = '<div class="address">退货地址：'.$detail['invoice_address'].'</div>';
						break;
				}
				array_push($logresult,$value);
			}
		}
		$detail['log'] = $logresult;
		return $this->ej_json_success($detail);
	}
	//卖家退款详情
/* 	function seller_view(){
		$refund_id = isset($_REQUEST['refund_id']) ? intval($_REQUEST['refund_id']) : 0;
		if(!$refund_id){
			return $this->ej_json_failed(2001);
		}
		//获取退款信息
		$sql = "select id,o.status as type,refund_sn,r.status,r.add_time,r.step,r.reason,r.postscript,r.invoice_address,r.amount from ".DB_PREFIX."order_refund as r left join ".DB_PREFIX."order as o on r.order_id = o.order_id where r.id = ".$refund_id." and r.status != ".REFUND_CANCEL;
		$model_order =& m('order');
		$detail = $model_order->getRow($sql);
		switch ($detail['status']) {
				case REFUND_APPLY:
					$detail['button'] = '<div class="refundContent"><div class="title"><span>等待卖家处理</span><span class="right"><em class="iconfont icon-bodadianhua"></em>联系卖家</span></div><div class="content">卖家在<span>6天23小时59分</span>内未处理，系统将自动同意退款。</div><div class="call"><span class="agree tongyituihuo">同意退款</span><span class="jujuetuikuan">拒绝退款</span></div></div>';
					if(if($detail['type']==30){
						$detail['button'] = '<div class="refundContent"><div class="title"><span>等待卖家处理</span><span class="right"><em class="iconfont icon-bodadianhua"></em>联系卖家</span></div><div class="content">卖家在<span>6天23小时59分</span>内未处理，系统将自动同意退款。</div><div class="call"><span class="agree tongyishenqing">同意申请</span><span class="jujueshenqing">拒绝退款</span></div></div>';
					}
					break;
				case REFUND_REFUSE:
					$detail['button'] = '<div class="refundContent"><div class="title"><span>退款关闭</span><span class="right"><em class="iconfont icon-bodadianhua"></em></span></div><div class="content">卖家拒绝退款，退款流程关闭，交易正常进行。</div></div>';
					if($detail['type'] == 30){
						$detail['button'] = '<div class="refundContent"><div class="title"><span>卖家拒绝退款</span><span class="right"><em class="iconfont icon-bodadianhua"></em></span></div><div class="content">买家在<span>6天23小时59分</span>内未重新申请退款，系统将自动取消退款</div></div>';
					}
					break;
				case REFUND_ACCESS:
					$detail['button'] = "";
					if($detail['type']==30){
						$value['button'] = '<div class="refundContent">
						<div class="title"><span>等待买家退货</span><span class="right"><em class="iconfont icon-bodadianhua"></em>联系卖家</span></div>
						<div class="content">卖家同意后，请及时退货</div>
						<div class="content">退货地址：'.'北京市朝阳区惠河南街泰丰汇（艺加君 收） 13512345678'.'</div>
						<div class="content">如您在<span>6天23小时59分</span>内未处理，将自动取消退款申请。</div>
						<div class="buyerCall"><span class="quxiaotuikuan qutuihuo">去退货</span></div>
						</div>';
					}
					break;
				case ORDER_SHIPPED:
					$detail['button'] = '<div class="refundContent"><div class="title"><span>等待卖家处理</span><span class="right"><em class="iconfont icon-bodadianhua"></em>联系卖家</span></div><div class="content">卖家在<span>6天23小时59分</span>内未处理，系统将自动同意退款。</div><div class="call"><span class="agree tongyituihuo">同意退款</span><span class="jujuetuikuan">拒绝退款</span></div></div>';
					break;
				case ORDER_FINISHED:
					$detail['button'] = '<div class="refundContent"><div class="title"><span>退款成功</span><span class="right"><em class="iconfont icon-bodadianhua"></em>联系卖家</span></div><div class="content">钱款已退回，请注意查收。</div><div class="buyerCall"><span class="quxiaotuikuan  qianquxiang">查看钱的去向</span></div></div>';
					break;
				case ORDER_CANCEL:
					$detail['button'] = '';
					break;
				default:
					$detail['button'] = '';
			}
		//协商记录
		$logsql = "select refund_id,refund_status,remark,log_time from ".DB_PREFIX."refund_log where refund_id = ".$refund_id." order by log_time";
		$logarr = $model_order->getAll($logsql);
		$logresult = array();
		if($logarr){
			foreach($logarr as $value){
				$value['content'] = '';
				$value['log_time'] = date('Y-m-d H:i:s',$value['log_time']);
				switch ($value['refund_status']) {
					case REFUND_APPLY:
						$value['content'] = '<ul><li>退款原因：'.$detail['reason'].'</li><li>退款金额：'.$detail['amount'].'元</li><li>退款说明：'.$detail['postscript'].'</li></ul>';
						break;
					case REFUND_SHIP:
						$value['content'] = '<div class="address">退货地址：'.$detail['invoice_address'].'</div>';
						break;
				}
				array_push($logresult,$value);
			}
		}
		$detail['log'] = $logresult;
		return $this->ej_json_success($detail);
	} */
	
	//买家取消退款
	function cancle_refund(){
		$refund_id = isset($_REQUEST['refund_id']) ? intval($_REQUEST['refund_id']) : 0;
		if(!$refund_id){
			return $this->ej_json_failed(2001);	
		}
		//判断是否由该订单发起
		$sql = "select id,rec_id,order_id,buyer_name,amount from ".DB_PREFIX."order_refund where id = ".$refund_id." and status != ".REFUND_CANCEL ." and buyer_id = ".$this->visitor->get('user_id');
		$model_order =& m('order');
		$refundarr = $model_order->getRow($sql);
		if(!$refundarr){
			return $this->ej_json_failed(3001);
		}
		//更改商品退款状态
		$model_ordergoods =& m('ordergoods');
		$editgoods = ['is_refund'=>0];
		$model_ordergoods->edit("rec_id=".$refundarr['rec_id']." AND order_id=".$refundarr['order_id'], $editgoods);
		//修改退款订单状态
		$editrefund = 'UPDATE '.DB_PREFIX.'order_refund SET status=0 where id = '.$refund_id;
		$model_order->db->query($editrefund);
		//记录日志
		$sqlfields = 'refund_log(refund_id,operator,refund_status,remark,log_time,refund_amount)';
		$insersql = "(".$refund_id.",'".$refundarr['buyer_name']."',".REFUND_CANCEL.",'买家取消退款',".time().",".$refundarr['amount'].")";
		$model_order->db->query('INSERT INTO '.DB_PREFIX.$sqlfields.' VALUES'.$insersql);
		return $this->ej_json_success();
	}
	//买家退货显示退货地址
	function ship_address(){
		$refund_id = isset($_REQUEST['refund_id']) ? intval($_REQUEST['refund_id']) : 0;
		if(!$refund_id){
			return $this->ej_json_failed(2001);	
		}
		//判断是否由该订单发起
		$sql = "select id,consignee,mobile,address from ".DB_PREFIX."order_refund where id = ".$refund_id." and status = ".REFUND_ACCESS ." and buyer_id = ".$this->visitor->get('user_id');
		$model_order =& m('order');
		$refundarr = $model_order->getRow($sql);
		if(empty($refundarr)){
			return $this->ej_json_failed(3001);	
		}
		return $this->ej_json_success($refundarr);
	}
	//买家提交退货接口
	function refund_ship(){
		$refund_id = isset($_REQUEST['refund_id']) ? intval($_REQUEST['refund_id']) : 0;
		$invoice_no = isset($_REQUEST['invoice_no']) ? trim($_REQUEST['invoice_no']) : '';
		$invoice_address = isset($_REQUEST['invoice_address']) ? trim($_REQUEST['invoice_address']) : '';
		if(!$refund_id || !$invoice_no || !$invoice_address){
			return $this->ej_json_failed(2001);	
		}
		//判断是否由该订单发起
		$sql = "select id,consignee,mobile,address,buyer_name,amount from ".DB_PREFIX."order_refund where id = ".$refund_id." and status = ".REFUND_ACCESS ." and buyer_id = ".$this->visitor->get('user_id');
		$model_order =& m('order');
		$refundarr = $model_order->getRow($sql);
		if(!$refundarr){
			return $this->ej_json_failed(3001);
		}
		//填写物流单号和物流公司
		$editrefund = 'UPDATE '.DB_PREFIX."order_refund SET step=3,invoice_no='".$invoice_no."',invoice_address = '".$invoice_address."',status =".REFUND_SHIP."  where id = ".$refund_id;
		$model_order->db->query($editrefund);
		//记录日志
		$sqlfields = 'refund_log(refund_id,operator,refund_status,remark,log_time,refund_amount)';
		$insersql = "(".$refund_id.",'".$refundarr['buyer_name']."',".REFUND_SHIP.",'买家已退货',".time().",".$refundarr['amount'].")";
		$model_order->db->query('INSERT INTO '.DB_PREFIX.$sqlfields.' VALUES'.$insersql);
		return $this->ej_json_success();
	}
	//卖家同意申请
	function agree_apply(){
		$refund_id = isset($_REQUEST['refund_id']) ? intval($_REQUEST['refund_id']) : 0;
		$consignee = isset($_REQUEST['consignee']) ? trim($_REQUEST['consignee']) : '';
		$mobile = isset($_REQUEST['mobile']) ? trim($_REQUEST['mobile']) : '';
		$address = isset($_REQUEST['address']) ? trim($_REQUEST['address']) : '';
		if(!$refund_id || !$consignee || !$mobile || !$address){
			return $this->ej_json_failed(2001);	
		}
		//判断是否由该订单发起
		$sql = "select id,seller_name,amount from ".DB_PREFIX."order_refund where id = ".$refund_id." and status = ".REFUND_APPLY ." and seller_id = ".$this->visitor->get('user_id');
		$model_order =& m('order');
		$refundarr = $model_order->getRow($sql);
		if(empty($refundarr)){
			return $this->ej_json_failed(3001);
		}
		//同意卖家退款
		$editrefund = 'UPDATE '.DB_PREFIX."order_refund SET step=2,consignee='".$consignee."',mobile = '".$mobile."',address='".$address."',status =".REFUND_ACCESS."  where id = ".$refund_id;
		$model_order->db->query($editrefund);
		//记录日志
		$sqlfields = 'refund_log(refund_id,operator,refund_status,remark,log_time,refund_amount)';
		$insersql = "(".$refund_id.",'".$refundarr['seller_name']."',".REFUND_ACCESS.",'卖家同意退款申请',".time().",".$refundarr['amount'].")";
		$model_order->db->query('INSERT INTO '.DB_PREFIX.$sqlfields.' VALUES'.$insersql);
		return $this->ej_json_success();
	}
	//卖家拒绝退款
	function refuse_apply(){
		$refund_id = isset($_REQUEST['refund_id']) ? intval($_REQUEST['refund_id']) : 0;
		$reason = isset($_REQUEST['reason']) ? trim($_REQUEST['reason']) : '';
		if(!$refund_id || !$reason){
			return $this->ej_json_failed(2001);	
		}
		//判断是否由该订单发起
		$sql = "select id,seller_name,amount,status from ".DB_PREFIX."order_refund where id = ".$refund_id." and status = ".REFUND_APPLY ." and seller_id = ".$this->visitor->get('user_id');
		$model_order =& m('order');
		$refundarr = $model_order->getRow($sql);
		if(empty($refundarr)){
			return $this->ej_json_failed(3001);
		}
		//判断step数目
		if($refundarr['status'] == 1){
			$step = 1;
		}else{
			$step = 4;
		}
		//卖家拒绝退款
		$editrefund = 'UPDATE '.DB_PREFIX."order_refund SET step={$step},status=".REFUND_REFUSE." where id = ".$refund_id;
		$model_order->db->query($editrefund);
		//记录日志
		$sqlfields = 'refund_log(refund_id,operator,refund_status,reason,remark,log_time,refund_amount)';
		$insersql = "(".$refund_id.",'".$refundarr['seller_name']."',".REFUND_REFUSE.",'".$reason."','卖家拒绝退款申请',".time().",".$refundarr['amount'].")";
		$model_order->db->query('INSERT INTO '.DB_PREFIX.$sqlfields.' VALUES'.$insersql);
		return $this->ej_json_success();	
	}
	//卖家同意退款,保证金额的准确性
	function do_refund(){
		$refund_id = isset($_REQUEST['refund_id']) ? intval($_REQUEST['refund_id']) : 0;
		if(!$refund_id){
			return $this->ej_json_failed(2001);	
		}
		//判断是否由该订单发起
		$sql = "select id,order_id,seller_name,amount,status from ".DB_PREFIX."order_refund where id = ".$refund_id." and seller_id = ".$this->visitor->get('user_id');
		$model_order =& m('order');
		$refundarr = $model_order->getRow($sql);
		if(empty($refundarr)){
			return $this->ej_json_failed(3001);
		}
		$orderid = $refundarr['order_id'];
		$order_info = $model_order->get(array(
			'conditions'    => $orderid
		));
		//检测订单是否符合退款条件
		if(empty($order_info)){
			return $this->ej_json_failed(3001);
		}
		if($order_info['status'] == 20 && $order_info['status'] = 30){
			return $this->ej_json_failed(3001);
		}
		//判断用户是否支付
		$sreamarr = $model_order->db->getRow("SELECT tran_id,pay_type FROM ".DB_PREFIX."order_stream WHERE order_id=".$orderid);
		if(empty($sreamarr)){
			return $this->ej_json_failed(3001);
		}
		switch ($sreamarr['pay_type'])
		{
			case 1://微信支付退款
			  $result = $this->_wxpay_reduce($order_info,$refundarr['amount']);
			  break;
			case 0://余额支付退款
			  $result = $this->_balpay_reduce($order_info,$refundarr['amount']);
			  break;
			default:
			  return $this->ej_json_failed(3001);
		}
		//退款流程完成
		if($result){
			//加回商品库存，当退回商品最后一件商品，该订单状态设置为已关闭
			$order_type =& ot($order_info['extension']);
			$order_detail = $order_type->get_order_detail($orderid, $order_info);
			$is_refund = '';
			foreach($order_detail['data']['goods_list'] as $value){
				if($value['is_refund'] == 0){
					$is_refund .= $value['rec_id'];
				}
			}
			//判断为全部退款
			if(!$is_refund){
			   $model_order->edit($orderid, [ 'status' => ORDER_CANCELED, 'cancel_time' => time() ]);
				/* 记录订单操作日志 */
				$order_log =& m('orderlog');
				$order_log->add([
					'order_id'       => $orderid,
					'operator'       => addslashes($this->visitor->get('user_name')),
					'order_status'   => order_status($order_info['status']),
					'changed_status' => order_status(ORDER_CANCELED),
					'remark'         => '该订单全部商品被退款',
					'log_time'       => gmtime(),
				]);
			}
			//更新商品库存
			$model_goodsspec =& m('goodsspec');
			$model_goods =& m('goods');
			$model_goodsspec->edit($order_detail['data']['goods_list'][$rec_id]['spec_id'], "stock=stock + ".$order_detail['data']['goods_list'][$rec_id]['quantity']);
			$model_goods->clear_cache($order_detail['data']['goods_list'][$rec_id]['goods_id']);
			//卖家完成退款
			$editrefund = 'UPDATE '.DB_PREFIX."order_refund SET step=5,status=".REFUND_FINISH." where id = ".$refund_id;
			$model_order->db->query($editrefund);
			//退款完成记录日志
			$sqlfields = 'refund_log(refund_id,operator,refund_status,remark,log_time,refund_amount)';
			$insersql = "(".$refund_id.",'".$refundarr['seller_name']."',".REFUND_FINISH.",'退款完成',".time().",".$refundarr['amount'].")";
			$model_order->db->query('INSERT INTO '.DB_PREFIX.$sqlfields.' VALUES'.$insersql);
		}
		return $this->ej_json_success();	
	}
	
	//微信支付退款
	function _wxpay_reduce($order_info,$refund_amount = 0){
		if(!$refund_amount){
			return $this->ej_json_failed(3001);
		}
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
		$attributes['trade_amount'] = $refund_amount * 100;
		$attributes['refundNo'] = '3'.$sreamarr['order_sn'];
		$attributes['tran_id'] = str_replace('fin','',$tran_id);
		$result = Wechat::reduce($attributes);
		if(!$result){
			return $this->ej_json_failed(3001);
		}
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
		$data['trade_amount'] = $refund_amount * 100;//单位取“分”
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
			'first'=>'您有一笔退款的订单，小艺已经把钱给您原路送回去了，请注意查收哦！',
			'reason'=>'平台已处理',
			'refund'=> $refund_amount."元",
			'remark'=>'如未收到请联系艺加拍卖客服 400-630-4180 ',
		];
		//获取相关提醒信息  进行提醒
		$result = Wechat::sendNotice($sreamarr['bopen_id'],REDUCE_BUYER,$datas,SITE_URL."/shop/html/order/orderDetail.html?orderId=".$order_info['order_id']."&type=0");
		return true;
	}
	//余额支付退款
	function _balpay_reduce($order_info,$refund_amount = 0){
		if(!$refund_amount){
			return $this->ej_json_failed(3001);
		}
		//获取卖家的openid，记录流水表
		$userModel =& m('member');
		$sellArr = $userModel->get([
			'conditions' => "user_id='".$order_info['seller_id']."'",
			'fields' => 'openid',
		]);
		$model_order =& m('order');
		//像流水表更新
		$model_order->db->query("UPDATE ".DB_PREFIX."order_stream SET sopen_id='".$sellArr['openid']."' WHERE order_id=".$order_info['order_id']);
		//查出流水向拍卖对接
		$sreamarr = $model_order->db->getRow("SELECT tran_id,bopen_id,sopen_id,trade_amount,order_sn,pay_type FROM ".DB_PREFIX."order_stream WHERE order_id=".$order_info['order_id']);
		$data['tran_id'] = $sreamarr['tran_id'];
		$data['open_id'] = $sreamarr['bopen_id'];//买家openid
		$data['trade_amount'] = $refund_amount * 100;//单位取“分”
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
			'first'=>'您有一笔退款订单，小艺已经把钱给您原路送回去了，请注意查收哦！',
			'reason'=>'平台已处理',
			'refund'=>$refund_amount."元",
			'remark'=>'如未收到请联系艺加拍卖客服 400-630-4180 ',
		];
		//获取相关提醒信息  进行提醒
		$result = Wechat::sendNotice($sreamarr['bopen_id'],REDUCE_BUYER,$datas,SITE_URL."/shop/html/order/orderDetail.html?orderId=".$order_info['order_id']."&type=0");
		return true;
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