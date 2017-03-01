<?php
/*******************************************************************************
 * 艺加商城
 *
 * (c)  2016  Gavin(田宇)  <tianyu_0723@hotmail.com>
 *
 ******************************************************************************/

/**
 *    买家的订单管理控制器
 *
 * @author    Garbin
 * @usage     none
 */
class Buyer_orderApp extends MemberbaseApp
{

		//var $ejstatus = array('0'=>'交易取消','11'=>'待付款','20'=>'待发货','30'=>'待收货','40'=>'交易完成');
		var $ejstatus = array('0'=>'交易关闭','11'=>'等待买家付款','20'=>'买家已付款','30'=>'卖家已发货','40'=>'交易完成');
        /**
         * 评价订单
         *
         * by Gavin 20161116
         */
        function ejEvaluate() {
			// 订单ID不能为空
        $order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
        $descStars = isset($_REQUEST['desc_stars']) ? intval($_REQUEST['desc_stars']) : 5; // 描述评级
        $logiStars = isset($_REQUEST['logi_stars']) ? intval($_REQUEST['logi_stars']) : 5; // 物流评级
        $servStars = isset($_REQUEST['serv_stars']) ? intval($_REQUEST['serv_stars']) : 5; // 服务评级
            if ( !$order_id ) {
                return $this->ej_json_failed(-1, Lang::get('no_such_order'));
            }

        // 现在改成传json 字符串
        $evaluationArr = json_decode(stripslashes($_REQUEST['evaluations']), true);
        if ( is_null($evaluationArr) ) return $this->ej_json_failed(2004);
        else $_POST['evaluations'] = $evaluationArr;

        /* 验证订单有效性 */
        $model_order =& m('order');
        $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
        if ( !$order_info ) {
            return $this->ej_json_failed(-1, Lang::get('no_such_order'));
        }
        if ( $order_info['status'] != ORDER_FINISHED ) {
            /* 不是已完成的订单，无法评价 */
            return $this->ej_json_failed(-1, Lang::get('cant_evaluate'));
        }
        if ( $order_info['evaluation_status'] != 0 ) {
            /* 已评价的订单 */
            return $this->ej_json_failed(-1, Lang::get('already_evaluate'));
        }

        // 订单的每个商品信息表
        $model_ordergoods =& m('ordergoods');

        if ( IS_POST ) {
            /* 写入评价 */
            // $rec_id 订单商品表的自增ID
            foreach ( $_POST['evaluations'] as $rec_id => $evaluation ) {
                if ( $evaluation['evaluation'] <= 0 || $evaluation['evaluation'] > 5 ) {
                    return $this->ej_json_failed(-1, Lang::get('evaluation_error'));
                }
                if ( $evaluation['evaluation'] < 3 ) {
                    $credit_value = -1;
                } else if ( $evaluation['evaluation'] < 5 ) {
                    $credit_value = 0;
                } else if ( $evaluation['evaluation'] == 5 ) {
                    $credit_value = 1;
                }

                // 是否有图评论
                $isImg = 0;

                // 更新评论图片关联评论 ID
                $img_id_arr = explode(',', $evaluation['img_id']);
                if ( !empty($img_id_arr) ) {
                    $isImg = 1;
                    $uploadedFileMod =& m('uploadedfile');
                    $uploadedFileMod->edit(db_create_in($img_id_arr, 'file_id'), [ 'item_id' => $rec_id ]);
                }

                // 更新订单中每个商品的信息
                $model_ordergoods->edit("rec_id={$rec_id} AND order_id={$order_id}",
                    [
                        'evaluation'   => $evaluation['evaluation'],
                        'comment'      => addslashes($evaluation['comment']),
                        'credit_value' => $credit_value,
                        'is_img'       => $isImg
                    ]
                );


                # Todo 发送微信消息
            }

            /* 更新订单评价状态 */
            $model_order->edit($order_id, [
                'evaluation_status' => 1,
                'evaluation_time'   => gmtime(),
                'desc_stars'        => $descStars,
                'logi_stars'        => $logiStars,
                'serv_stars'        => $servStars,
            ]);

            // 获取商家的平均评分
            $avg = $model_order->getOne("select AVG(desc_stars) desc_stars, AVG(logi_stars) logi_stars, AVG(serv_stars) serv_stars from ecm_order where seller_id = {$order_info['seller_id']} and evaluation_status = 1");

            /* 更新卖家信用度,好评率,平均评分 */
            $model_store =& m('store');
            $model_store->edit($order_info['seller_id'], [
                'credit_value' => $model_store->recount_credit_value($order_info['seller_id']),
                'praise_rate'  => $model_store->recount_praise_rate($order_info['seller_id']),
                'desc_stars' => $avg['desc_stars'],
                'logi_stars' => $avg['logi_stars'],
                'serv_stars' => $avg['serv_stars'],
            ]);

            /* 更新商品评价数 */
            $model_goodsstatistics =& m('goodsstatistics');
            $goods_ids = [];
            $goods_list = $model_ordergoods->find("order_id={$order_id}");
            foreach ( $goods_list as $goods ) {
                $goods_ids[] = $goods['goods_id'];
            }
            $model_goodsstatistics->edit($goods_ids, 'comments=comments+1');

            $this->ej_json_success();
        } else {
            // 必须post请求
            return $this->ej_json_failed(-1, 2001);
        }

    }

    /**
     * 删除评价图片
     */
    function ejDropImage()
    {
        $fileID = empty($_REQUEST['file_id']) ? 0 : intval($_REQUEST['file_id']);
        $uploadedFileMod =& m('uploadedfile');
        $store_id = intval($this->visitor->get('manage_store'));
        $uploadedFile = $uploadedFileMod->get([
            'conditions' => "f.file_id = '$fileID' AND f.store_id = '{$store_id}'",
            'fields'     => 'f.file_id,f.cloud_image_id',
        ]);

        if ( $uploadedFile ) {
            $uploadedFileMod->drop($fileID);
            if ( $uploadedFile['cloud_image_id'] ) {
                \Tencentyun\ImageV2::del(CLOUD_IMAGE_BUCKET, $uploadedFile['cloud_image_id']);
            }

            return $this->ej_json_success();
        }

        return $this->ej_json_failed(-1, Lang::get('no_image_droped'));
    }

    function index()
    {
        /* 获取订单列表 */
        $orderlist = $this->_get_orders();

        return $this->ej_json_success($orderlist);
    }

    /**
     *    查看订单详情
     *
     * @author    Garbin
     * @return    void
     */
    function view()
    {
        $order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : 0;
        $type = isset($_REQUEST['type']) ? intval($_REQUEST['type']) : 0;
        $model_order =& m('order');
        if ( $type == 0 ) {
            $order_info = $model_order->get([
                'fields'     => "*, order.add_time as order_add_time",
                'conditions' => "order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'),
                'join'       => 'belongs_to_store',
            ]);
        } else {
            $order_info = $model_order->get([
                'fields'     => "*, order.add_time as order_add_time",
                'conditions' => "order_id={$order_id} AND seller_id=" . $this->visitor->get('user_id'),
                'join'       => 'belongs_to_store',
            ]);
        }

        if ( !$order_info ) {
            return $this->ej_json_failed(2001);
        }

        /* 团购信息 去掉团购信息 详情见app目录下的相同处理*/
        /* 调用相应的订单类型，获取整个订单详情数据 */
        $order_type =& ot($order_info['extension']);
        $order_detail = $order_type->get_order_detail($order_id, $order_info);
        foreach ( $order_detail['data']['goods_list'] as $key => $goods ) {
            empty($goods['goods_image']) && $order_detail['data']['goods_list'][ $key ]['goods_image'] = Conf::get('default_goods_image');
        }
        $result['order_id'] = $order_info['order_id'];
        $result['order_sn'] = $order_info['order_sn'];
        $result['invoice_no'] = empty($order_info['invoice_no']) ? '' : trim($order_info['invoice_no']);
        $result['seller_id'] = $order_info['seller_id'];
        $result['seller_name'] = $order_info['seller_name'];
        $result['buyer_id'] = $order_info['buyer_id'];
        $result['buyer_name'] = $order_info['buyer_name'];
        $result['status'] = $order_info['status'];
        $result['order_add_time'] = empty($order_info['order_add_time']) ? '' : date('Y-m-d H:i:s', $order_info['order_add_time']);
        $result['finished_time'] = empty($order_info['finished_time']) ? '' : date('Y-m-d H:i:s', $order_info['finished_time']);
        //'0'=>'交易取消','11'=>'等待买家付款','20'=>'买家已付款','30'=>'卖家已发货','40'=>'交易完成'
        $times = gmtime();
        switch ( $order_info['status'] ) {
            case 0://已取消
                $result['statusname'] = $this->ejstatus[ $order_info['status'] ];
                $result['lefttime'] = '';//剩余时间
                $canceltime = empty($order_info['cancel_time']) ? '' : date('Y-m-d H:i', $order_info['cancel_time']);
                $result['topshow'] = "<div class='dTop'><div class='dTopL'><i class='iconfont icon-wancheng'></i></div><div class='dTopR'><span>交易失败</span><p>关闭时间：：<time>" . $canceltime . "</time></p></div></div>";
                break;
            case 11://代付款
                $result['statusname'] = $this->ejstatus['11'];
                $paramtime = $order_info['order_add_time'] + 172800;
                $result['lefttime'] = ejlefttime($paramtime, $times);//剩余时间
                if ( $type == 0 ) {
                    $result['topshow'] = "<div class='dTop'><div class='dTopL'><i class='iconfont icon-dengdai'></i></div><div class='dTopR'><span>待付款</span><p><time>" . date('Y-m-d', $paramtime) . "</time> 后订单将自动关闭，请及时付款！</p></div></div>";
                } else {
                    $result['topshow'] = "<div class='dTop'><div class='dTopL'><i class='iconfont icon-dengdai'></i></div><div class='dTopR'><span>待付款</span><p><time>" . date('Y-m-d', $paramtime) . "</time> 后订单将自动关闭！</p></div></div>";
                }
                break;
            case 20://代发货
                $result['statusname'] = $this->ejstatus['20'];
                $result['lefttime'] = '';//剩余时间
                if ( $type == 0 ) {
                    $ejpaytime = empty($order_info['pay_time']) ? '' : date('Y-m-d H:i', $order_info['pay_time']);
                    $result['topshow'] = "<div class='dTop'><div class='dTopL'><i class='iconfont icon-wancheng'></i></div><div class='dTopR'><span>待发货</span><p> 付款时间：<time>" . $ejpaytime . "</time></p></div></div>";
                } else {
                    $result['topshow'] = "<div class='dTop'><div class='dTopL'><i class='iconfont icon-wancheng'></i></div><div class='dTopR'><span>待发货</span><p>请及时给买家发货哦！</p></div></div>";
                }
                break;
            case 30://代收货
                $result['statusname'] = $this->ejstatus['20'];
                if ( $order_info['add_shiptime'] == 1 ) {//判断用户是否延长收货  默认延长收货为7天
                    $sumshiptime = $order_info['ship_time'] + EJADD_SHIP * 86400 + 7 * 86400;
                } else {
                    $sumshiptime = $order_info['ship_time'] + 7 * 86400;
                }
                $result['statusname'] = $this->ejstatus['30'];
                $result['lefttime'] = ejlefttime($sumshiptime, $times);//剩余时间
                if ( $type == 0 ) {
                    $result['topshow'] = "<div class='dTop'><div class='dTopL'><i class='iconfont icon-qufahuo'></i></div><div class='dTopR'><span>待收货</span><p><time>" . date('Y-m-d H:i', $sumshiptime) . "</time> 后订单将自动确认收货，请确保已收到商品！</p></div></div>";
                } else {
                    $result['topshow'] = "<div class='dTop'><div class='dTopL'><i class='iconfont icon-qufahuo'></i></div><div class='dTopR'><span>待收货</span><p><time>" . date('Y-m-d H:i', $sumshiptime) . "</time> 后订单将自动确认收货！</p></div></div>";
                }
                break;
            case 40://待评价
                $result['statusname'] = $this->ejstatus['40'];
                $result['lefttime'] = '';//剩余时间
                $result['finished_time'] = empty($order_info['finished_time']) ? '' : date('Y-m-d H:i', $order_info['finished_time']);
                $result['topshow'] = "<div class='dTop'><div class='dTopL'><i class='iconfont icon-wancheng'></i></div><div class='dTopR'><span>交易完成</span><p>完成时间：<time>" . $result['finished_time'] . "</time></p></div></div>";
                break;
            default:
                $result['statusname'] = '交易取消';
                $result['lefttime'] = '';//剩余时间
                $canceltime = empty($order_info['cancel_time']) ? '' : date('Y-m-d H:i', $order_info['cancel_time']);
                $result['topshow'] = "<div class='dTop'><div class='dTopL'><i class='iconfont icon-wancheng'></i></div><div class='dTopR'><span>交易失败</span><p>关闭时间：：<time>" . $canceltime . "</time></p></div></div>";
        }
        //添加订单详情页面按钮
        if ( $type == 1 ) {
            switch ( $result['status'] ) {
                case 20://代发货
                    $result['button'] = "<div class='dfixed'><a class='qufahuo'>去发货</a></div>";
                    break;
                case 30://代收货
                    $result['button'] = "<div class='dfixed'><a class='chakanwuliu'>查看物流</a><a class='tixingshouhuo'>提醒收货</a></div>";
                    break;
                case 40://待评价
                    $result['button'] = "<div class='dfixed'><a class='chakanwuliu'>查看物流</a></div>";
                    break;
                default:
                    $result['button'] = '';
            }
        } else {
            switch ( $result['status'] ) {
                case 11://代付款
                    $result['button'] = "<div class='dfixed dfixed2'><a class='quxiaodingdan'>取消订单</a><a class='qufukuan'>去付款</a></div>";
                    break;
                case 20://代发货
                    $result['button'] = "<div class='dfixed'><a class='tixingfahuo'>提醒发货</a></div>";
                    break;
                case 30://代收货
                    $result['button'] = "<div class='dfixed dfixed3'><a class='yanchangshouhuo'>延长收货</a><a class='chakanwuliu'>查看物流</a><a class='querenshouhuo'>确认收货</a></div>";
                    break;
                case 40://待评价
                    $result['button'] = "<div class='dfixed'><a class='chakanwuliu'>查看物流</a></div>";
                    break;
                default:
                    $result['button'] = '';
            }
        }
        $result['pay_time'] = empty($order_info['pay_time']) ? '' : date('Y-m-d H:i:s', $order_info['pay_time']);//付款时间
        $result['order_amount'] = $order_info['order_amount'];
        $result['consignee'] = $order_detail['data']['order_extm'];
        $result['goods_list'] = $order_detail['data']['goods_list'];

        return $this->ej_json_success($result);
    }

    /**
     *    取消订单
     *
     * @author    Garbin
     * @return    void
     */
    function cancel_order( $orderid = 0, $remark = '' )
    {
        $order_id = isset($_REQUEST['order_id']) ? intval($_REQUEST['order_id']) : $orderid;
        if ( !$order_id ) {
            return $this->ej_json_failed(2001);
        }
        $model_order =& m('order');
        /* 只有待付款的订单可以取消 */
        $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id') . " AND status " . db_create_in([ ORDER_PENDING, ORDER_SUBMITTED ]));
        if ( empty($order_info) ) {
            return $this->ej_json_failed(1012);
        }
        $model_order->edit($order_id, [ 'status' => ORDER_CANCELED, 'cancel_time' => time() ]);
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

        /* 发送给卖家订单取消通知 */

        return $this->ej_json_success();
    }

    /**
     *    确认订单
     *
     * @author    newrain
     * @return    void
     */
    function confirm_order( $orderid = 0 )
    {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : $orderid;
        if ( !$order_id ) {
            return $this->ej_json_failed(2001);
        }
        $model_order =& m('order');
        /* 只有已发货的订单可以确认 */
        $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id') . " AND status=" . ORDER_SHIPPED);
        if ( $order_info['if_fronzen'] >= 1 ) {
            return $this->ej_json_failed(1023);
        }
        if ( empty($order_info) ) {
            return $this->ej_json_failed(3001);
        }
        $model_order->edit($order_id, [ 'status' => ORDER_FINISHED, 'finished_time' => gmtime() ]);
        if ( $model_order->has_error() ) {
            return $this->ej_json_failed(3001);
        }
        /* 记录订单操作日志 */
        $order_log =& m('orderlog');
        $order_log->add([
            'order_id'       => $order_id,
            'operator'       => addslashes($this->visitor->get('user_name')),
            'order_status'   => order_status($order_info['status']),
            'changed_status' => order_status(ORDER_FINISHED),
            'remark'         => Lang::get('buyer_confirm'),
            'log_time'       => gmtime(),
        ]);
        /*TODO 发送给卖家买家微信推送，交易完成 */
        /* 更新累计销售件数 */
        $model_goodsstatistics =& m('goodsstatistics');
        $model_ordergoods =& m('ordergoods');
        $order_goods = $model_ordergoods->find("order_id={$order_id}");
        foreach ( $order_goods as $goods ) {
            $model_goodsstatistics->edit($goods['goods_id'], "sales=sales+{$goods['quantity']}");
            $sales_total = $goods['quantity'] * $goods['price'];
            $model_goodsstatistics->edit($goods['goods_id'], "sales_total=sales_total+{$sales_total}");
        }
        //获取卖家的openid，记录流水表
        $userModel =& m('member');
        $sellArr = $userModel->get([
            'conditions' => "user_id='" . $order_info['seller_id'] . "'",
            'fields'     => 'openid',
        ]);
        //像流水表更新
        $model_order->db->query("UPDATE " . DB_PREFIX . "order_stream SET sopen_id='" . $sellArr['openid'] . "' WHERE order_id=$order_id");
        //查出流水向拍卖对接
        $sreamarr = $model_order->db->getRow("SELECT tran_id,bopen_id,sopen_id,trade_amount,order_sn,pay_type FROM " . DB_PREFIX . "order_stream WHERE order_id=$order_id");
        $data['tran_id'] = $sreamarr['tran_id'];
        $data['open_id'] = $sreamarr['sopen_id'];
        $data['buyer'] = $sreamarr['bopen_id'];
        $data['trade_amount'] = $sreamarr['trade_amount'];
        $data['pay_type'] = $sreamarr['pay_type'];
        $data['order_sn'] = $sreamarr['order_sn'];
        $data['title'] = '订单支付';
        $data['trade_type'] = 28;
        $data['order_id'] = $order_id;
        include ROOT_PATH . '/includes/aes.base.php';
        $serialjson = Security::encrypt(json_encode($data), 'yijiawang.com#@!');
    	$this->_confirmcurl(array('data'=>$serialjson));
        //推送卖家确认收货消息
        $data = [
            'first'    => '报告主人，订单' . $sreamarr['order_sn'] . '买家已确认收货，小艺把钱已装入您的钱包,请注意查收哦！',
            'keyword1' => $sreamarr['order_sn'],
            'keyword2' => ( $sreamarr['trade_amount'] / 100 ) . "元",
            'keyword3' => date('Y-m-d H:i'),
            'remark'   => '请点击"详情"查看更多，如有任何疑问请联系我们。',
        ];
        //获取相关提醒信息  进行提醒
        $result = Wechat::sendNotice($sellArr['openid'], CONFIRM_SELLER, $data, SITE_URL . "/shop/html/order/orderDetail.html?orderId=" . $order_id . "&type=1");

        return $this->ej_json_success();
    }

    /**
     *    给卖家评价
     *
     * @author    Garbin
     *
     * @param    none
     *
     * @return    void
     */
    function evaluate()
    {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if ( !$order_id ) {
            $this->show_warning('no_such_order');

            return;
        }

        /* 验证订单有效性 */
        $model_order =& m('order');
        $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
        if ( !$order_info ) {
            $this->show_warning('no_such_order');

            return;
        }
        if ( $order_info['status'] != ORDER_FINISHED ) {
            /* 不是已完成的订单，无法评价 */
            $this->show_warning('cant_evaluate');

            return;
        }
        if ( $order_info['evaluation_status'] != 0 ) {
            /* 已评价的订单 */
            $this->show_warning('already_evaluate');

            return;
        }
        $model_ordergoods =& m('ordergoods');

        if ( !IS_POST ) {
            /* 显示评价表单 */
            /* 获取订单商品 */
            $goods_list = $model_ordergoods->find("order_id={$order_id}");
            foreach ( $goods_list as $key => $goods ) {
                empty($goods['goods_image']) && $goods_list[ $key ]['goods_image'] = Conf::get('default_goods_image');
            }
            $this->_curlocal(LANG::get('member_center'), 'index.php?app=member',
                LANG::get('my_order'), 'index.php?app=buyer_order',
                LANG::get('evaluate'));
            $this->assign('goods_list', $goods_list);
            $this->assign('order', $order_info);

            $this->_config_seo('title', Lang::get('member_center') . ' - ' . Lang::get('credit_evaluate'));
            $this->display('buyer_order.evaluate.html');
        } else {
            $evaluations = [];
            /* 写入评价 */
            foreach ( $_POST['evaluations'] as $rec_id => $evaluation ) {
                if ( $evaluation['evaluation'] <= 0 || $evaluation['evaluation'] > 3 ) {
                    $this->show_warning('evaluation_error');

                    return;
                }
                switch ( $evaluation['evaluation'] ) {
                    case 3:
                        $credit_value = 1;
                        break;
                    case 1:
                        $credit_value = -1;
                        break;
                    default:
                        $credit_value = 0;
                        break;
                }
                $evaluations[ intval($rec_id) ] = [
                    'evaluation'   => $evaluation['evaluation'],
                    'comment'      => addslashes($evaluation['comment']),
                    'credit_value' => $credit_value
                ];
            }
            $goods_list = $model_ordergoods->find("order_id={$order_id}");
            foreach ( $evaluations as $rec_id => $evaluation ) {
                $model_ordergoods->edit("rec_id={$rec_id} AND order_id={$order_id}", $evaluation);
                $goods_url = SITE_URL . '/' . url('app=goods&id=' . $goods_list[ $rec_id ]['goods_id']);
                $goods_name = $goods_list[ $rec_id ]['goods_name'];
                $this->send_feed('goods_evaluated', [
                    'user_id'    => $this->visitor->get('user_id'),
                    'user_name'  => $this->visitor->get('user_name'),
                    'goods_url'  => $goods_url,
                    'goods_name' => $goods_name,
                    'evaluation' => Lang::get('order_eval.' . $evaluation['evaluation']),
                    'comment'    => $evaluation['comment'],
                    'images'     => [
                        [
                            'url'  => SITE_URL . '/' . $goods_list[ $rec_id ]['goods_image'],
                            'link' => $goods_url,
                        ],
                    ],
                ]);
            }

            /* 更新订单评价状态 */
            $model_order->edit($order_id, [
                'evaluation_status' => 1,
                'evaluation_time'   => gmtime()
            ]);

            /* 更新卖家信用度及好评率 */
            $model_store =& m('store');
            $model_store->edit($order_info['seller_id'], [
                'credit_value' => $model_store->recount_credit_value($order_info['seller_id']),
                'praise_rate'  => $model_store->recount_praise_rate($order_info['seller_id'])
            ]);

            /* 更新商品评价数 */
            $model_goodsstatistics =& m('goodsstatistics');
            $goods_ids = [];
            foreach ( $goods_list as $goods ) {
                $goods_ids[] = $goods['goods_id'];
            }
            $model_goodsstatistics->edit($goods_ids, 'comments=comments+1');


            $this->show_message('evaluate_successed',
                'back_list', 'index.php?app=buyer_order');
        }
    }

        /**
         *    获取订单列表
         *
         * @author    Garbin
         * @return    void
         */
        function _get_orders() {
            $page = $this->_get_page(10);
            $model_order =& m('order');
            !$_GET['type'] && $_GET['type'] = 'all_orders';
            $con = [
                [      //按订单状态搜索
                    'field'   => 'status',
                    'name'    => 'type',
                    'handler' => 'order_status_translator',
                ],
                [      //按店铺名称搜索
                    'field' => 'seller_name',
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
            ];
			$conditions = $this->_get_query_conditions($con);
			//按照商品搜索
			$search_bygoods = isset( $_REQUEST['search_goodsname'] ) ? trim($_REQUEST['search_goodsname']) : '';
			if($search_bygoods){
				$search_bygoodsql = "SELECT o.order_id from ".DB_PREFIX."order_goods as og left join ".DB_PREFIX."order as o on og.order_id = o.order_id  WHERE o.buyer_id=" . $this->visitor->get('user_id')." and og.goods_name like '%".$search_bygoods."%'";
				$bygoods_orderarr = $model_order->db->getAll($search_bygoodsql);
				if($bygoods_orderarr){
					$endarr = end($bygoods_orderarr);
					$search_bygoodstr = '';
					foreach($bygoods_orderarr as $value){
						if($endarr == $value){
							$search_bygoodstr .= $value['order_id'];
						}else{
							$search_bygoodstr .= $value['order_id'].',';
						}
					}
					$conditions .= " AND order_alias.order_id in (".$search_bygoodstr.")";
				}else{
					$conditions .= " AND order_alias.order_id in (0)";
				}
			}
			if($_GET['type'] == 'finished'){
				$conditions .= " AND order_alias.evaluation_status = 0 ";
			}
            /* 查找订单 */
            $orders = $model_order->findAll([
                'conditions' => "buyer_id=" . $this->visitor->get('user_id') . "{$conditions}",
                'fields'     => 'this.*',
                'count'      => true,
                'limit'      => $page['limit'],
                'order'      => 'add_time DESC',
                'include'    => [
					'has_ordergoods',       //取出商品
                ],
            ]);
			//'0'=>'交易取消','11'=>'等待买家付款','20'=>'买家已付款','30'=>'卖家已发货','40'=>'交易完成'
			$result = array();
			if($orders){
				foreach ( $orders as $key1 => $order ) {
					$temp['order_id'] = $order['order_id'];
					$temp['seller_id'] = $order['seller_id'];
					$temp['invoice_no'] = empty($order['invoice_no'])?'':trim($order['invoice_no']);
					$temp['seller_name'] = $order['seller_name'];
					$temp['buyer_id'] = $order['buyer_id'];
					$temp['status'] = $order['status'];
					$temp['statusname'] = $this->ejstatus[$order['status']];
					switch ($order['status']) {
						case 11:
							$temp['button'] = "<div class='dOperate'><a class='quxiaodingdan'>取消订单</a><a class='qufukuan'>去付款</a></div>";
							break;
						case 20:
							$temp['button'] = "<div class='dOperate'><a class='tixingfahuo'>提醒发货</a></div>";
							break;
						case 30:
							$temp['button'] = "<div class='dOperate'><a class='yanchangshouhuo'>延长收货</a><a class='chakanwuliu'>查看物流</a><a class='querenshouhuo'>确认收货</a></div>";
							break;
						case 40:
							$temp['button'] = "<div class='dOperate'><a class='chakanwuliu'>查看物流</a><a class='qupingjia'>去评价</a></div>";
							if($order['evaluation_status'] == 1){
								$temp['button'] = "<div class='dOperate'><a class='chakanwuliu'>查看物流</a></div>";
							}
							break;
						default:
							$temp['button'] = '';
					}
					$temp['order_amount'] = $order['order_amount'];
					$tmpgoods = array();
					foreach($order['order_goods'] as $v){
						$tmp['rec_id'] = $v['rec_id'];
						$tmp['order_id'] = $v['order_id'];
						$tmp['goods_id'] = $v['goods_id'];
						$tmp['goods_name'] = $v['goods_name'];
						$tmp['quantity'] = $v['quantity'];
						$tmp['price'] = $v['price'];
						$tmp['goods_image'] = $v['goods_image'];
						array_push($tmpgoods,$tmp);
					}
					$temp['order_goods'] = $tmpgoods;
					array_push($result,$temp);
				}
			}
            $page['item_count'] = $model_order->getCount();
			$res['orderlist'] = $result;
			$res['page'] = $page;
			return $res;
        }

        function _get_member_submenu() {
            $menus = [
                [
                    'name' => 'order_list',
                    'url'  => 'index.php?app=buyer_order',
                ],
            ];

            return $menus;
        }
		
	   /**
		 *    响应支付
		 *
		 *    @author    Garbin
		 *    @param     int    $order_id
		 *    @param     array  $notify_result
		 *    @return    bool
		 */
		function respond_notify($order_id, $notify_result)
		{
			$model_order =& m('order');
			$where = "order_id = {$order_id}";
			$data = array('status' => $notify_result['target']);
			switch ($notify_result['target'])
			{
				case ORDER_ACCEPTED:
					$where .= ' AND status=' . ORDER_PENDING;   //只有待付款的订单才会被修改为已付款
					$data['pay_time']   =   gmtime();
				break;
				case ORDER_SHIPPED:
					$where .= ' AND status=' . ORDER_ACCEPTED;  //只有等待发货的订单才会被修改为已发货
					$data['ship_time']  =   gmtime();
				break;
				case ORDER_FINISHED:
					$where .= ' AND status=' . ORDER_SHIPPED;   //只有已发货的订单才会被自动修改为交易完成
					$data['finished_time'] = gmtime();
				break;
				case ORDER_CANCLED:                             //任何情况下都可以关闭
					/* 加回商品库存 */
					$model_order->change_stock('+', $order_id);
				break;
			}
			return $model_order->edit($where, $data);
		}
		
		/**
         *    延长订单收货接口
         *
         * @author    newrain
         * @return    void
         */
        function delayship() {
            $order_id = isset( $_GET['order_id'] ) ? intval($_GET['order_id']) : 0;
            if ( !$order_id ) {
				return $this->ej_json_failed(2001);
            }
			$delayval = 1;
            $model_order =& m('order');
            /* 只有已发货的订单可以确认 */
            $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id') . " AND status=" . ORDER_SHIPPED);
			if($order_info['if_fronzen'] >= 1){
				return $this->ej_json_failed(1023);
			}
            if ( empty( $order_info ) ) {
				return $this->ej_json_failed(3001);
            }else{
				//当用户已经点击过延长，再次点击为取消延长收货
				if($order_info['add_shiptime']){
					return $this->ej_json_failed(1008);
				}
			}
			$model_order->edit($order_id, [ 'add_shiptime' => $delayval ]);
			if ( $model_order->has_error() ) {
				return $this->ej_json_failed(3001);
			}
			/* 记录订单操作日志 */
			$order_log =& m('orderlog');
			$order_log->add([
				'order_id'       => $order_id,
				'operator'       => addslashes($this->visitor->get('user_name')),
				'order_status'   => order_status($order_info['status']),
				'changed_status' => order_status($order_info['status']),
				'remark'         => '延长收货',
				'log_time'       => gmtime(),
			]);
			/*TODO 发送给卖家买家微信推送，交易完成 */
			return $this->ej_json_success();
        }
		
		//提醒卖家发货
		function remindship(){
			$order_id = isset($_GET['order_id'] ) ? intval($_GET['order_id']) : 0;
            if ( !$order_id ) {
				return $this->ej_json_failed(2001);
            }
			//获取订单信息
			$model_order =& m('order');
            $order_info = $model_order->get("order_id={$order_id} AND buyer_id=" . $this->visitor->get('user_id'));
			if($order_info['if_fronzen'] >= 1){
				return $this->ej_json_failed(1023);
			}
			if(empty($order_info)){
				return $this->ej_json_failed(3001);
			}
			//判断是否属于待付款的状态
			if($order_info['status'] != ORDER_ACCEPTED){
				return $this->ej_json_failed(3001);
			}
			$remindtime = time();
			if($order_info['remindtime'] == 0){
				$model_order->edit($order_id, [ 'remindtime' => $remindtime ]);
			}else{
				$diffremindtime = $remindtime - $order_info['remindtime'];
				if($diffremindtime >= 86400){
					$model_order->edit($order_id, [ 'remindtime' => $remindtime ]);
				}else{
					return $this->ej_json_failed(1007);
				}
			}
			//获取商家openid
			$model_member =& m('member');
            $member_info = $model_member->get("user_id=".$order_info['seller_id']." AND user_id !=" . $this->visitor->get('user_id'));
			/*TODO 发送给卖家买家微信推送，交易完成 */
			$templateid = REMIND_SELLER;//消息模板id
			$topenid = $member_info['openid'];
			$data = [
				'first'=>'买家已经催单了，老板赶紧去发货吧!',
				'keyword1'=>$order_info['order_sn'],
				'keyword2'=>$order_info['order_amount'],
				'keyword3'=>$order_info['buyer_name'],
				'keyword4'=>'已支付',
				'remark'=>'客户已经付款，老板快去发货吧',
			];
			$result = Wechat::sendNotice($topenid,$templateid,$data,SITE_URL."/shop/html/order/orderDetail.html?orderId=".$order_id."&type=1");
			return $this->ej_json_success();
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
