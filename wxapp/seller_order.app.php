<?php

    /**
     *    卖家的订单管理控制器
     *
     * @author    newrain
     * @usage     none
     */
    class Seller_orderApp extends StoreadminbaseApp {
		var $ejstatus = array('0'=>'交易关闭','11'=>'等待买家付款','20'=>'买家已付款','30'=>'卖家已发货','40'=>'交易完成');
        /**
         * 回复商品评价
         *
         * by Gavin 20161116
         */
        public function ejReplyEvaluate() {
            // 订单商品ID不能为空
            $rec_id = isset( $_REQUEST['rec_id'] ) ? intval($_REQUEST['rec_id']) : 0;
            $reply = isset( $_REQUEST['reply'] ) ? $_REQUEST['reply'] : '';
            if ( empty( $rec_id ) || empty( $reply ) ) {
                return $this->ej_json_failed(2001);
            }

            $orderGoodsModel =& m('ordergoods');
            $orderGoodsArr = $orderGoodsModel->find([
                'conditions' => "rec_id = {$rec_id} AND is_valid = 1 AND evaluation_status = 1 ",
                'join'       => 'belongs_to_order',
                'fields'     => 'status,evaluation_status,is_reply',
                'count'      => false,
            ]);

            if ( empty( $orderGoodsArr ) ) {
                return $this->ej_json_failed(1020);
            }
            if ( $orderGoodsArr['status'] != ORDER_FINISHED ) {
                /* 不是已完成的订单，无法评价 */
                return $this->ej_json_failed(-1, Lang::get('cant_evaluate'));
            }
            if ( $orderGoodsArr['evaluation_status'] != 1 ) {
                /* 买家未评价 */
                return $this->ej_json_failed(1022);
            }
            if ( $orderGoodsArr['is_reply'] != 0 ){
                // 已回复
                return $this->ej_json_failed(1021);
            }

            if ( IS_POST ) {
                /* 写入回复 */
                $orderGoodsModel->edit($rec_id, [
                    'is_reply' => 1,
                    'reply'    => $reply,
                ]);

                # Todo 发送消息

                $this->ej_json_success();
            } else {
                // 必须post请求
                return $this->ej_json_failed(-1, 2001);
            }

        }

        function index() {
            /* 获取订单列表 */
            $result = $this->_get_orders();
			return $this->ej_json_success($result);
        }

        /**
         *    查看订单详情
         *
         * @author    Garbin
         * @return    void
         */
        function view() {
            $order_id = isset( $_GET['order_id'] ) ? intval($_GET['order_id']) : 0;

            $model_order =& m('order');
            $order_info = $model_order->findAll([
                'conditions' => "order_alias.order_id={$order_id} AND seller_id=" . $this->visitor->get('manage_store'),
                'join'       => 'has_orderextm',
            ]);
            $order_info = current($order_info);
            if ( !$order_info ) {
                $this->show_warning('no_such_order');

                return;
            }

            /* 团购信息 */
            if ( $order_info['extension'] == 'groupbuy' ) {
                $groupbuy_mod = &m('groupbuy');
                $group = $groupbuy_mod->get([
                    'join'       => 'be_join',
                    'conditions' => 'order_id=' . $order_id,
                    'fields'     => 'gb.group_id',
                ]);
                $this->assign('group_id', $group['group_id']);
            }

            /* 当前位置 */
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=member',
                LANG::get('order_manage'), 'index.php?app=seller_order',
                LANG::get('view_order'));

            /* 当前用户中心菜单 */
            $this->_curitem('order_manage');
            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('detail'));

            /* 调用相应的订单类型，获取整个订单详情数据 */
            $order_type =& ot($order_info['extension']);
            $order_detail = $order_type->get_order_detail($order_id, $order_info);
            $spec_ids = [];
            foreach ( $order_detail['data']['goods_list'] as $key => $goods ) {
                empty( $goods['goods_image'] ) && $order_detail['data']['goods_list'][ $key ]['goods_image'] = Conf::get('default_goods_image');
                $spec_ids[] = $goods['spec_id'];

            }

            /* 查出最新的相应的货号 */
            $model_spec =& m('goodsspec');
            $spec_info = $model_spec->find([
                'conditions' => $spec_ids,
                'fields'     => 'sku',
            ]);
            foreach ( $order_detail['data']['goods_list'] as $key => $goods ) {
                $order_detail['data']['goods_list'][ $key ]['sku'] = $spec_info[ $goods['spec_id'] ]['sku'];
            }

            $this->assign('order', $order_info);
            $this->assign($order_detail['data']);
            $this->display('seller_order.view.html');
        }

        /**
         *    收到货款
         *
         * @author    Garbin
         *
         * @param    none
         *
         * @return    void
         */
        function received_pay() {
            list( $order_id, $order_info ) = $this->_get_valid_order_info(ORDER_PENDING);
            if ( !$order_id ) {
                echo Lang::get('no_such_order');

                return;
            }
            if ( !IS_POST ) {
                header('Content-Type:text/html;charset=' . CHARSET);
                $this->assign('order', $order_info);
                $this->display('seller_order.received_pay.html');
            } else {
                $model_order =& m('order');
                $model_order->edit(intval($order_id), [ 'status' => ORDER_ACCEPTED, 'pay_time' => gmtime() ]);
                if ( $model_order->has_error() ) {
                    $this->pop_warning($model_order->get_error());

                    return;
                }

                #TODO 发邮件通知
                /* 记录订单操作日志 */
                $order_log =& m('orderlog');
                $order_log->add([
                    'order_id'       => $order_id,
                    'operator'       => addslashes($this->visitor->get('user_name')),
                    'order_status'   => order_status($order_info['status']),
                    'changed_status' => order_status(ORDER_ACCEPTED),
                    'remark'         => $_POST['remark'],
                    'log_time'       => gmtime(),
                ]);

                /* 发送给买家邮件，提示等待安排发货 */
                $model_member =& m('member');
                $buyer_info = $model_member->get($order_info['buyer_id']);
                $mail = get_mail('tobuyer_offline_pay_success_notify', [ 'order' => $order_info ]);
                $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

                $new_data = [
                    'status'  => Lang::get('order_accepted'),
                    'actions' => [
                        'cancel',
                        'shipped'
                    ], //可以取消可以发货
                ];

                $this->pop_warning('ok');
            }

        }

        /**
         *    货到付款的订单的确认操作
         *
         * @author    Garbin
         *
         * @param    none
         *
         * @return    void
         */
        function confirm_order() {
            list( $order_id, $order_info ) = $this->_get_valid_order_info(ORDER_SUBMITTED);
            if ( !$order_id ) {
                echo Lang::get('no_such_order');

                return;
            }
            if ( !IS_POST ) {
                header('Content-Type:text/html;charset=' . CHARSET);
                $this->assign('order', $order_info);
                $this->display('seller_order.confirm.html');
            } else {
                $model_order =& m('order');
                $model_order->edit($order_id, [ 'status' => ORDER_ACCEPTED ]);
                if ( $model_order->has_error() ) {
                    $this->pop_warning($model_order->get_error());

                    return;
                }

                /* 记录订单操作日志 */
                $order_log =& m('orderlog');
                $order_log->add([
                    'order_id'       => $order_id,
                    'operator'       => addslashes($this->visitor->get('user_name')),
                    'order_status'   => order_status($order_info['status']),
                    'changed_status' => order_status(ORDER_ACCEPTED),
                    'remark'         => $_POST['remark'],
                    'log_time'       => gmtime(),
                ]);

                /* 发送给买家邮件，订单已确认，等待安排发货 */
                $model_member =& m('member');
                $buyer_info = $model_member->get($order_info['buyer_id']);
                $mail = get_mail('tobuyer_confirm_cod_order_notify', [ 'order' => $order_info ]);
                $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

                $new_data = [
                    'status'  => Lang::get('order_accepted'),
                    'actions' => [
                        'cancel',
                        'shipped'
                    ], //可以取消可以发货
                ];

                $this->pop_warning('ok');;
            }
        }

        /**
         *    调整费用
         *
         * @author    Garbin
         * @return    void
         */
        function adjust_fee() {
            list( $order_id, $order_info ) = $this->_get_valid_order_info([ ORDER_SUBMITTED, ORDER_PENDING ]);
            if ( !$order_id ) {
                echo Lang::get('no_such_order');

                return;
            }
            $model_order =& m('order');
            $model_orderextm =& m('orderextm');
            $shipping_info = $model_orderextm->get($order_id);
            if ( !IS_POST ) {
                header('Content-Type:text/html;charset=' . CHARSET);
                $this->assign('order', $order_info);
                $this->assign('shipping', $shipping_info);
                $this->display('seller_order.adjust_fee.html');
            } else {
                /* 配送费用 */
                $shipping_fee = isset( $_POST['shipping_fee'] ) ? abs(floatval($_POST['shipping_fee'])) : 0;
                /* 折扣金额 */
                $goods_amount = isset( $_POST['goods_amount'] ) ? abs(floatval($_POST['goods_amount'])) : 0;
                /* 订单实际总金额 */
                $order_amount = round($goods_amount + $shipping_fee, 2);
                if ( $order_amount <= 0 ) {
                    /* 若商品总价＋配送费用扣队折扣小于等于0，则不是一个有效的数据 */
                    $this->pop_warning('invalid_fee');

                    return;
                }
                $data = [
                    'goods_amount' => $goods_amount,    //修改商品总价
                    'order_amount' => $order_amount,     //修改订单实际总金额
                    'pay_alter'    => 1    //支付变更
                ];

                if ( $shipping_fee != $shipping_info['shipping_fee'] ) {
                    /* 若运费有变，则修改运费 */

                    $model_extm =& m('orderextm');
                    $model_extm->edit($order_id, [ 'shipping_fee' => $shipping_fee ]);
                }
                $model_order->edit($order_id, $data);

                if ( $model_order->has_error() ) {
                    $this->pop_warning($model_order->get_error());

                    return;
                }
                /* 记录订单操作日志 */
                $order_log =& m('orderlog');
                $order_log->add([
                    'order_id'       => $order_id,
                    'operator'       => addslashes($this->visitor->get('user_name')),
                    'order_status'   => order_status($order_info['status']),
                    'changed_status' => order_status($order_info['status']),
                    'remark'         => Lang::get('adjust_fee'),
                    'log_time'       => gmtime(),
                ]);

                /* 发送给买家邮件通知，订单金额已改变，等待付款 */
                $model_member =& m('member');
                $buyer_info = $model_member->get($order_info['buyer_id']);
                $mail = get_mail('tobuyer_adjust_fee_notify', [ 'order' => $order_info ]);
                $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

                $new_data = [
                    'order_amount' => price_format($order_amount),
                ];

                $this->pop_warning('ok');
            }
        }

        /**
         * 待发货的订单发货
         *
         * @author    newrain
         * @return    void
         */
        function shipped() {	
			$order_id = isset( $_GET['order_id'] ) ? intval($_GET['order_id']) : 0;
			$invoice_no = isset( $_GET['invoice_no'] ) ? intval($_GET['invoice_no']) : 0;
			if ( !$order_id || !$invoice_no) {
				return $this->ej_json_failed(2001);
            }
            $model_order =& m('order');
            $order_info = $model_order->findAll([
                'conditions' => "order_alias.order_id={$order_id} AND seller_id=" . $this->visitor->get('manage_store'),
                'join'       => 'has_orderextm',
            ]);
			if($order_info[$order_id]['if_fronzen'] >= 1){
				return $this->ej_json_failed(1023);
			}
			//判断订单详情是否为空
			if(empty($order_info)){
				return $this->ej_json_failed(2001);
			}
			//非本店卖家不能操作
			if($order_info[$order_id]['seller_id'] != $this->visitor->get('user_id') ){
				return $this->ej_json_failed(3001);
			}
			//判断当前状态是否可以发货
			if($order_info[$order_id]['status'] != ORDER_ACCEPTED && $order_info[$order_id]['status'] != ORDER_SUBMITTED){			
				return $this->ej_json_failed(3001);
			}
			$edit_data = [ 'status' => ORDER_SHIPPED, 'invoice_no' => $invoice_no ];
			$is_edit = true;
			if ( empty( $order_info[$order_id]['invoice_no'] ) ) {
				/* 不是修改发货单号 */
				$edit_data['ship_time'] = gmtime();
				$is_edit = false;
			}
			$model_order->edit(intval($order_id), $edit_data);
			if ( $model_order->has_error() ) {
				return $this->ej_json_failed(3001);
			}

			#TODO 发邮件通知
			/* 记录订单操作日志 */
			$order_log =& m('orderlog');
			$order_log->add([
				'order_id'       => $order_id,
				'operator'       => addslashes($this->visitor->get('user_name')),
				'order_status'   => order_status($order_info[$order_id]['status']),
				'changed_status' => order_status(ORDER_SHIPPED),
				'remark'         => $_REQUEST['remark'],
				'log_time'       => gmtime(),
			]);
			/* TODO 微信通知发送给买家订单已发货通知   测试代码*/
			//获取买家openid
			$model_member =& m('member');
            $member_info = $model_member->get("user_id=".$order_info[$order_id]['buyer_id']." AND user_id !=" . $this->visitor->get('user_id'));
			/*TODO 发送给卖家买家微信推送 */
			$templateid = SHIP_SELLER;//消息模板id
			$topenid = $member_info['openid'];
			//获取货物单号信息
			require ROOT_PATH.'/includes/Http.php';
			$http = new Http();
			$url ='http://highapi.kuaidi.com/openapi-querycountordernumber.html';
			$jsonArr = $http->parseJSON($http->get($url, [
				'id' => '12f3f629d0d68fbe3bc8370843961c31',
				'nu' => $invoice_no
			]));
			if(!isset($jsonArr['company'])){
				$jsonArr['company'] = '货物配送';
			}
			$data = [
				'first'=>'主人，小艺已穿戴整齐带着您拍下的商品，快马加鞭向您狂奔而来,您耐心等下哟~',
				'keyword1'=>$order_info[$order_id]['order_sn'],
				'keyword2'=>$jsonArr['company'],
				'keyword3'=>$invoice_no,
				'keyword4'=>$order_info[$order_id]['consignee'].$order_info[$order_id]['address'],
				'remark'=>'请您耐心等待',
			];
			$result = Wechat::sendNotice($topenid,$templateid,$data,SITE_URL."/shop/html/order/orderDetail.html?orderId=".$order_id."&type=0");	
			
			return $this->ej_json_success();
        }

        /**
         *    取消订单
         *
         * @author    Garbin
         * @return    void
         */
        function cancel_order() {
            /* 取消的和完成的订单不能再取消 */
            //list($order_id, $order_info)    = $this->_get_valid_order_info(array(ORDER_SUBMITTED, ORDER_PENDING, ORDER_ACCEPTED, ORDER_SHIPPED));
            $order_id = isset( $_GET['order_id'] ) ? trim($_GET['order_id']) : '';
            if ( !$order_id ) {
                echo Lang::get('no_such_order');
            }
            $status = [ ORDER_SUBMITTED, ORDER_PENDING, ORDER_ACCEPTED, ORDER_SHIPPED ];
            $order_ids = explode(',', $order_id);
            if ( $ext ) {
                $ext = ' AND ' . $ext;
            }

            $model_order =& m('order');
            /* 只有已发货的货到付款订单可以收货 */
            $order_info = $model_order->find([
                'conditions' => "order_id" . db_create_in($order_ids) . " AND seller_id=" . $this->visitor->get('manage_store') . " AND status " . db_create_in($status) . $ext,
            ]);
            $ids = array_keys($order_info);
            if ( !$order_info ) {
                echo Lang::get('no_such_order');

                return;
            }
            if ( !IS_POST ) {
                header('Content-Type:text/html;charset=' . CHARSET);
                $this->assign('orders', $order_info);
                $this->assign('order_id', count($ids) == 1 ? current($ids) : implode(',', $ids));
                $this->display('seller_order.cancel.html');
            } else {
                $model_order =& m('order');
                foreach ( $ids as $val ) {
                    $id = intval($val);
                    $model_order->edit($id, [ 'status' => ORDER_CANCELED ]);
                    if ( $model_order->has_error() ) {
                        //$_erros = $model_order->get_error();
                        //$error = current($_errors);
                        //$this->json_error(Lang::get($error['msg']));
                        //return;
                        continue;
                    }

                    /* 加回订单商品库存 */
                    $model_order->change_stock('+', $id);
                    $cancel_reason = ( !empty( $_POST['remark'] ) ) ? $_POST['remark'] : $_POST['cancel_reason'];
                    /* 记录订单操作日志 */
                    $order_log =& m('orderlog');
                    $order_log->add([
                        'order_id'       => $id,
                        'operator'       => addslashes($this->visitor->get('user_name')),
                        'order_status'   => order_status($order_info[ $id ]['status']),
                        'changed_status' => order_status(ORDER_CANCELED),
                        'remark'         => $cancel_reason,
                        'log_time'       => gmtime(),
                    ]);

                    /* 发送给买家订单取消通知 */
                    $model_member =& m('member');
                    $buyer_info = $model_member->get($order_info[ $id ]['buyer_id']);
                    $mail = get_mail('tobuyer_cancel_order_notify', [ 'order' => $order_info[ $id ], 'reason' => $_POST['remark'] ]);
                    $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

                    $new_data = [
                        'status'  => Lang::get('order_canceled'),
                        'actions' => [], //取消订单后就不能做任何操作了
                    ];
                }
                $this->pop_warning('ok', 'seller_order_cancel_order');
            }

        }

        /**
         *    完成交易(货到付款的订单)
         *
         * @author    Garbin
         * @return    void
         */
        function finished() {
            list( $order_id, $order_info ) = $this->_get_valid_order_info(ORDER_SHIPPED, 'payment_code=\'cod\'');
            if ( !$order_id ) {
                echo Lang::get('no_such_order');

                return;
            }
            if ( !IS_POST ) {
                header('Content-Type:text/html;charset=' . CHARSET);
                /* 当前用户中心菜单 */
                $this->_curitem('seller_order');
                /* 当前所处子菜单 */
                $this->_curmenu('finished');
                $this->assign('_curmenu', 'finished');
                $this->assign('order', $order_info);
                $this->display('seller_order.finished.html');
            } else {
                $now = gmtime();
                $model_order =& m('order');
                $model_order->edit($order_id, [ 'status' => ORDER_FINISHED, 'pay_time' => $now, 'finished_time' => $now ]);
                if ( $model_order->has_error() ) {
                    $this->pop_warning($model_order->get_error());

                    return;
                }

                /* 记录订单操作日志 */
                $order_log =& m('orderlog');
                $order_log->add([
                    'order_id'       => $order_id,
                    'operator'       => addslashes($this->visitor->get('user_name')),
                    'order_status'   => order_status($order_info['status']),
                    'changed_status' => order_status(ORDER_FINISHED),
                    'remark'         => $_POST['remark'],
                    'log_time'       => gmtime(),
                ]);

                /* 更新累计销售件数 */
                $model_goodsstatistics =& m('goodsstatistics');
                $model_ordergoods =& m('ordergoods');
                $order_goods = $model_ordergoods->find("order_id={$order_id}");
                foreach ( $order_goods as $goods ) {
                    $model_goodsstatistics->edit($goods['goods_id'], "sales=sales+{$goods['quantity']}");
                }


                /* 发送给买家交易完成通知，提示评论 */
                $model_member =& m('member');
                $buyer_info = $model_member->get($order_info['buyer_id']);
                $mail = get_mail('tobuyer_cod_order_finish_notify', [ 'order' => $order_info ]);
                $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

                $new_data = [
                    'status'  => Lang::get('order_finished'),
                    'actions' => [], //完成订单后就不能做任何操作了
                ];

                $this->pop_warning('ok');
            }

        }

        /**
         *    获取有效的订单信息
         *
         * @author    Garbin
         *
         * @param     array  $status
         * @param     string $ext
         *
         * @return    array
         */
        function _get_valid_order_info( $status, $ext = '' ) {
            $order_id = isset( $_GET['order_id'] ) ? intval($_GET['order_id']) : 0;
            if ( !$order_id ) {

                return [];
            }
            if ( !is_array($status) ) {
                $status = [ $status ];
            }

            if ( $ext ) {
                $ext = ' AND ' . $ext;
            }

            $model_order =& m('order');
            /* 只有已发货的货到付款订单可以收货 */
            $order_info = $model_order->get([
                'conditions' => "order_id={$order_id} AND seller_id=" . $this->visitor->get('manage_store') . " AND status " . db_create_in($status) . $ext,
            ]);
            if ( empty( $order_info ) ) {

                return [];
            }

            return [ $order_id, $order_info ];
        }

        /**
         *    获取订单列表
         *
         * @author    Garbin
         * @return    void
         */
        function _get_orders() {
            $page = $this->_get_page();
            $model_order =& m('order');
            !$_GET['type'] && $_GET['type'] = 'all_orders';
            $conditions = '';
            // 团购订单  暂时去掉团购部分代码为满足v1.0  
            $conditions .= $this->_get_query_conditions([
                [      //按订单状态搜索
                    'field'   => 'status',
                    'name'    => 'type',
                    'handler' => 'order_status_translator',
                ],
                [      //按买家名称搜索
                    'field' => 'buyer_name',
                    'equal' => 'LIKE',
                ],
                [      //按下单时间搜索,起始时间
                    'field'   => 'add_time',
                    'name'    => 'add_time_from',
                    'equal'   => '>=',
                    'handler' => 'gmstr2time',
                ],
                [      //按下单时间搜索,结束时间
                    'field'   => 'add_time',
                    'name'    => 'add_time_to',
                    'equal'   => '<=',
                    'handler' => 'gmstr2time_end',
                ],
                [      //按订单号
                    'field' => 'order_sn',
                ],
            ]);

            /* 查找订单 */
            $orders = $model_order->findAll([
                'conditions' => "seller_id=" . $this->visitor->get('manage_store') . "{$conditions}",
                'count'      => true,
                'join'       => 'has_orderextm',
                'limit'      => $page['limit'],
                'order'      => 'add_time DESC',
                'include'    => [
                    'has_ordergoods',       //取出商品
                ],
            ]);
			$result = array();
			if($orders){
				foreach ( $orders as $value ) {
					$temp['order_id'] = $value['order_id'];
					$temp['order_sn'] = $value['order_sn'];
					$temp['invoice_no'] = empty($value['invoice_no'])?'':trim($value['invoice_no']);
					$temp['seller_id'] = $value['seller_id'];
					$temp['seller_name'] = $value['seller_name'];
					$temp['buyer_name'] = $value['buyer_name'];
					$temp['status'] = $value['status'];
					$temp['statusname'] = $this->ejstatus[$value['status']];
					switch ($value['status']) {
						case 20:
							$temp['button'] = "<div class='dOperate'><a class='qufahuo'>去发货</a></div>";
							break;
						case 30:
							$temp['button'] = "<div class='dOperate'><a class='chakanwuliu'>查看物流</a><a class='tixingshouhuo'>提醒收货</a></div>";
							break;
						case 40:
							$temp['button'] = "<div class='dOperate'><a class='chakanwuliu'>查看物流</a></div>";
							break;
						default:
							$temp['button'] = '';
					}
					$temp['order_amount'] = $value['order_amount'];
					$tmparr = array();
					foreach ( $value['order_goods'] as $v ) {
						$tmp['rec_id'] = $v['rec_id'];
						$tmp['order_id'] = $v['order_id'];
						$tmp['goods_id'] = $v['goods_id'];
						$tmp['goods_name'] = $v['goods_name'];
						$tmp['goods_image'] = $v['goods_image'];
						$tmp['price'] = $v['price'];
						$tmp['quantity'] = $v['quantity'];
						$tmp['is_reply'] = $v['is_reply'];
						$tmp['reply'] = $v['reply'];
						array_push($tmparr,$tmp);
					}
					$temp['order_goods'] = $tmparr;
					array_push($result,$temp);
				}
			}
            $page['item_count'] = $model_order->getCount();
			$res['orderlist'] = $result;
			$res['page'] = $page;
			return $res;
        }

        /*三级菜单*/
        function _get_member_submenu() {
            $array = [
                [
                    'name' => 'all_orders',
                    'url'  => 'index.php?app=seller_order&amp;type=all_orders',
                ],
                [
                    'name' => 'pending',
                    'url'  => 'index.php?app=seller_order&amp;type=pending',
                ],
                [
                    'name' => 'submitted',
                    'url'  => 'index.php?app=seller_order&amp;type=submitted',
                ],
                [
                    'name' => 'accepted',
                    'url'  => 'index.php?app=seller_order&amp;type=accepted',
                ],
                [
                    'name' => 'shipped',
                    'url'  => 'index.php?app=seller_order&amp;type=shipped',
                ],
                [
                    'name' => 'finished',
                    'url'  => 'index.php?app=seller_order&amp;type=finished',
                ],
                [
                    'name' => 'canceled',
                    'url'  => 'index.php?app=seller_order&amp;type=canceled',
                ],
            ];

            return $array;
        }
		//提醒买家收货
		function remindreceipt(){
			$order_id = isset($_GET['order_id'] ) ? intval($_GET['order_id']) : 0;
            if ( !$order_id ) {
				return $this->ej_json_failed(2001);
            }
			//获取订单信息
			$model_order =& m('order');
            $order_info = $model_order->get("order_id={$order_id} AND seller_id=" . $this->visitor->get('user_id'));
			if($order_info['if_fronzen'] >= 1){
				return $this->ej_json_failed(1023);
			}
			if(empty($order_info)){
				return $this->ej_json_failed(3001);
			}
			//判断是否属于待付款的状态
			if($order_info['status'] != ORDER_SHIPPED){
				return $this->ej_json_failed(3001);
			}
			//将提醒收货存入redis ，设置失效期为24小时
			if(Cache::get('mseller_'.$order_id)){
				return $this->ej_json_failed(1007);
			}
			Cache::set('mseller_'.$order_id,1,86400);
			//获取商家openid*/
			$model_member =& m('member');
            $member_info = $model_member->get("user_id=".$order_info['buyer_id']." AND user_id !=" . $this->visitor->get('user_id'));
			/*TODO 发送给卖家买家微信推送，交易完成*/ 
			$topenid = $member_info['openid'];
			//推送卖家确认收货消息
			$data = [
				'first'=>'亲，感谢您对'.$order_info['seller_name'].'的惠顾，收到宝贝后，请您点击确认收货 ^_^',
				'keyword1'=>$order_info['order_sn'],
				'keyword2'=>$order_info['order_amount']."元",
				'keyword3'=>date('Y-m-d H:i'),
				'remark'=>'请点击"详情"查看更多，如有任何疑问请联系我们。',
			];
			//获取相关提醒信息  进行提醒
			$result = Wechat::sendNotice($topenid,CONFIRM_SELLER,$data,SITE_URL."/shop/html/order/orderDetail.html?orderId=".$order_id."&type=0");
			return $this->ej_json_success();
		}
    }

?>
