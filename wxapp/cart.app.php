<?php

/**
 *    购物车控制器，负责会员购物车的管理工作，她与下一步售货员的接口是：购物车告诉售货员，我要买的商品是我购物车内的商品
 *
 * @author    Garbin
 */
class CartApp extends MallbaseApp
{

    /**
     * 获取购物车列表
     *
     * by Gavin 20161114
     */
    function ejIndex()
    {
        $store_id = isset( $_REQUEST['store_id'] ) ? intval($_REQUEST['store_id']) : 0;
        $carts = $this->_get_carts($store_id);

        return $this->ej_json_success($carts);
    }

    /**
     * 购物车商品数量
     *
     * by Gavin
     */
    function ejCount()
    {
        $store_id = isset( $_REQUEST['store_id'] ) ? intval($_REQUEST['store_id']) : 0;
        $count = $this->_get_cart_count($store_id);

        if ( is_null($count) || empty( $count ) ) $count = 0;

        return $this->ej_json_success([ 'count' => $count ]);
    }

    /**
     * 添加到购物车
     *
     * by Gavin 20161114
     */
    function ejAdd()
    {
        $spec_id = isset( $_REQUEST['spec_id'] ) ? intval($_REQUEST['spec_id']) : 0;
        $quantity = isset( $_REQUEST['quantity'] ) ? intval($_REQUEST['quantity']) : 0;

        if ( !$this->visitor->has_login ) {
            # Todo ...
            // 未登录添加购物车,放入redis,带登录添加
//            Cache::hset($_SESSION['wx_openid'],'waiting_cart_add',$spec_id.'|'.$quantity);
            return $this->ej_json_failed(3005);
        }

        if ( !$spec_id || !$quantity ) {
            // 参数错误
            return $this->ej_json_failed(2001);
        }

        /* 是否有商品 */
        $spec_model =& m('goodsspec');
        $spec_info = $spec_model->get([
            'fields'     => 'g.store_id, g.goods_id, g.goods_name, g.spec_name_1, g.spec_name_2, g.default_image, gs.spec_1, gs.spec_2, gs.stock, gs.price',
            'conditions' => $spec_id,
            'join'       => 'belongs_to_goods',
        ]);

        if ( !$spec_info ) {
            /* 商品不存在 */
            return $this->ej_json_failed(-1, Lang::get('no_such_goods'));
        }

        /* 如果是自己店铺的商品，则不能购买 */
        if ( $this->visitor->get('manage_store') ) {
            if ( $spec_info['store_id'] == $this->visitor->get('manage_store') ) {
                return $this->ej_json_failed(-1, Lang::get('can_not_buy_yourself'));
            }
        }

        // 数量不足
        if ( $quantity > $spec_info['stock'] ) {
            return $this->ej_json_failed(-1, Lang::get('no_enough_goods'));
        }

        $cartModel =& m('cart');
//        $cartData = $cartModel->get("spec_id={$spec_id} AND session_id='" . SESS_ID . "'");
        $cartData = $cartModel->get("spec_id={$spec_id} AND user_id='" . $this->visitor->get('user_id') . "'");
        // 如果数据库为空就添加购物车
        if ( empty( $cartData ) ) {
            $spec_1 = $spec_info['spec_name_1'] ? $spec_info['spec_name_1'] . ':' . $spec_info['spec_1'] : $spec_info['spec_1'];
            $spec_2 = $spec_info['spec_name_2'] ? $spec_info['spec_name_2'] . ':' . $spec_info['spec_2'] : $spec_info['spec_2'];
            $specification = $spec_1 . ' ' . $spec_2;
            /* 将商品加入购物车 */
            $cart_item = [
                'user_id'       => $this->visitor->get('user_id'),
                'session_id'    => SESS_ID,
                'store_id'      => $spec_info['store_id'],
                'spec_id'       => $spec_id,
                'goods_id'      => $spec_info['goods_id'],
                'goods_name'    => addslashes($spec_info['goods_name']),
                'specification' => addslashes(trim($specification)),
                'price'         => $spec_info['price'],
                'quantity'      => $quantity,
                'goods_image'   => addslashes($spec_info['default_image']),
            ];
            $cartModel->add($cart_item);
        } else {
            // 购物车中商品数量大于或等于库存数量 就不允许再添加
            if ( $cartData['quantity'] >= $spec_info['stock'] ) {
                return $this->ej_json_failed(-1, Lang::get('no_enough_goods'));
            }
            // 如果数据库已存在,就更新商品数量
            $cartData['rec_id'] && $cartModel->edit($cartData['rec_id'], "quantity=quantity+$quantity");
        }

        // 获取购物车信息
        $cart_status = $this->_get_cart_status();

        /* 更新被添加进购物车的次数 */
        $model_goodsstatistics =& m('goodsstatistics');
        $model_goodsstatistics->edit($spec_info['goods_id'], 'carts=carts+1');

        // 返回购物车状态
        return $this->ej_json_success($cart_status['status']);
    }

    /**
     * 更新购物车商品数量
     *
     * by Gavin 20161114
     */
    function ejUpdate()
    {
        $spec_id = isset( $_REQUEST['spec_id'] ) ? intval($_REQUEST['spec_id']) : 0;
        $quantity = isset( $_REQUEST['quantity'] ) ? intval($_REQUEST['quantity']) : 0;
        if ( !$spec_id || !$quantity ) {
            // 参数错误
            return $this->ej_json_failed(2001);
        }

        /* 判断库存是否足够 */
        $model_spec =& m('goodsspec');
        $spec_info = $model_spec->get($spec_id);
        if ( empty( $spec_info ) ) {
            return $this->ej_json_failed(-1, Lang::get('no_such_spec'));
        }

        /**
         * 当前购物车大于库存数时,直接更新到库存数
         */
        $isAgain = false;
        if($quantity > $spec_info['stock'] + 1){
            $quantity = $spec_info['stock'];
            $isAgain = true;
        }else if ( $quantity > $spec_info['stock'] ) {
            return $this->ej_json_failed(-1, Lang::get('no_enough_goods'));
        }

        /* 修改数量 */
//        $where = "spec_id={$spec_id} AND session_id='" . SESS_ID . "'";
        $where = "spec_id={$spec_id} AND user_id='" . $this->visitor->get('user_id') . "'";
        $model_cart =& m('cart');

        /* 获取购物车中的信息，用于获取价格并计算小计 */
        $cart_spec_info = $model_cart->get($where);
        if ( empty( $cart_spec_info ) ) {
            /* 并没有添加该商品到购物车 */
            return $this->ej_json_failed(1010);
        }

        $store_id = $cart_spec_info['store_id'];

        /* 修改数量 */
        $model_cart->edit($where, [
            'quantity' => $quantity,
        ]);

        /* 小计 */
        $subtotal = $quantity * $cart_spec_info['price'];

        /* 返回JSON结果 */
        $cart_status = $this->_get_cart_status();

        $ret = [
            'cart'     => $cart_status['status'],                     //返回总的购物车状态
            'subtotal' => $subtotal,                                  //小计
            'amount'   => $cart_status['carts'][ $store_id ]['amount']  //店铺购物车总计
        ];

        if ( $isAgain ) {
            $ret['operation'] = [
                'quantity' => $quantity
            ];

            return $this->ej_json_failed(1011, null, $ret);
        } else {
            return $this->ej_json_success($ret);
        }
    }

    /**
     * 删除购物车中商品
     *
     * by Gavin 20161114
     */
    function ejDrop()
    {
        /* 传入rec_id，删除并返回购物车统计即可 */
        $rec_id = isset( $_REQUEST['rec_id'] ) ? intval($_REQUEST['rec_id']) : 0;
        if ( !$rec_id ) {
            return $this->ej_json_failed(2001);
        }

        /* 从购物车中删除 */
        $model_cart =& m('cart');
        $droped_rows = $model_cart->drop('rec_id=' . $rec_id . ' AND session_id=\'' . SESS_ID . '\'', 'store_id');
        if ( !$droped_rows ) {
            return $this->ej_json_failed(1010);
        }

        /* 返回结果 */
        $dropped_data = $model_cart->getDroppedData();
        $store_id = $dropped_data[ $rec_id ]['store_id'];
        $cart_status = $this->_get_cart_status();

        $ret = [
            'cart'   => $cart_status['status'],                      //返回总的购物车状态
            'amount' => $cart_status['carts'][ $store_id ]['amount']   //返回指定店铺的购物车状态
        ];

        return $this->ej_json_success($ret);
    }

    /**
     *    列出购物车中的商品
     *
     * @author    Garbin
     * @return    void
     */
    function index()
    {
        $store_id = isset( $_GET['store_id'] ) ? intval($_GET['store_id']) : 0;
        $carts = $this->_get_carts($store_id);
        $this->_curlocal(
            LANG::get('cart')
        );
        $this->_config_seo('title', Lang::get('confirm_goods') . ' - ' . Conf::get('site_title'));

        if ( empty( $carts ) ) {
            $this->_cart_empty();

            return;
        }

        $this->assign('carts', $carts);
        $this->display('cart.index.html');
    }

    /**
     *    放入商品(根据不同的请求方式给出不同的返回结果)
     *
     * @author    Garbin
     * @return    void
     */
    function add()
    {
        $spec_id = isset( $_GET['spec_id'] ) ? intval($_GET['spec_id']) : 0;
        $quantity = isset( $_GET['quantity'] ) ? intval($_GET['quantity']) : 0;
        if ( !$spec_id || !$quantity ) {
            return;
        }

        /* 是否有商品 */
        $spec_model =& m('goodsspec');
        $spec_info = $spec_model->get([
            'fields'     => 'g.store_id, g.goods_id, g.goods_name, g.spec_name_1, g.spec_name_2, g.default_image, gs.spec_1, gs.spec_2, gs.stock, gs.price',
            'conditions' => $spec_id,
            'join'       => 'belongs_to_goods',
        ]);

        if ( !$spec_info ) {
            $this->json_error('no_such_goods');

            /* 商品不存在 */

            return;
        }

        /* 如果是自己店铺的商品，则不能购买 */
        if ( $this->visitor->get('manage_store') ) {
            if ( $spec_info['store_id'] == $this->visitor->get('manage_store') ) {
                $this->json_error('can_not_buy_yourself');

                return;
            }
        }

        /* 是否添加过 */
        $model_cart =& m('cart');
        $item_info = $model_cart->get("spec_id={$spec_id} AND session_id='" . SESS_ID . "'");
        if ( !empty( $item_info ) ) {
            $this->json_error('goods_already_in_cart');

            return;
        }

        if ( $quantity > $spec_info['stock'] ) {
            $this->json_error('no_enough_goods');

            return;
        }

        $spec_1 = $spec_info['spec_name_1'] ? $spec_info['spec_name_1'] . ':' . $spec_info['spec_1'] : $spec_info['spec_1'];
        $spec_2 = $spec_info['spec_name_2'] ? $spec_info['spec_name_2'] . ':' . $spec_info['spec_2'] : $spec_info['spec_2'];

        $specification = $spec_1 . ' ' . $spec_2;

        /* 将商品加入购物车 */
        $cart_item = [
            'user_id'       => $this->visitor->get('user_id'),
            'session_id'    => SESS_ID,
            'store_id'      => $spec_info['store_id'],
            'spec_id'       => $spec_id,
            'goods_id'      => $spec_info['goods_id'],
            'goods_name'    => addslashes($spec_info['goods_name']),
            'specification' => addslashes(trim($specification)),
            'price'         => $spec_info['price'],
            'quantity'      => $quantity,
            'goods_image'   => addslashes($spec_info['default_image']),
        ];

        /* 添加并返回购物车统计即可 */
        $cart_model =& m('cart');
        $cart_model->add($cart_item);
        $cart_status = $this->_get_cart_status();

        /* 更新被添加进购物车的次数 */
        $model_goodsstatistics =& m('goodsstatistics');
        $model_goodsstatistics->edit($spec_info['goods_id'], 'carts=carts+1');

        $this->json_result([
            'cart' => $cart_status['status'],  //返回购物车状态
        ], 'addto_cart_successed');
    }

    /**
     *    丢弃商品
     *
     * @author    Garbin
     * @return    void
     */
    function drop()
    {
        /* 传入rec_id，删除并返回购物车统计即可 */
        $rec_id = isset( $_GET['rec_id'] ) ? intval($_GET['rec_id']) : 0;
        if ( !$rec_id ) {
            return;
        }

        /* 从购物车中删除 */
        $model_cart =& m('cart');
        $droped_rows = $model_cart->drop('rec_id=' . $rec_id . ' AND session_id=\'' . SESS_ID . '\'', 'store_id');
        if ( !$droped_rows ) {
            return;
        }

        /* 返回结果 */
        $dropped_data = $model_cart->getDroppedData();
        $store_id = $dropped_data[ $rec_id ]['store_id'];
        $cart_status = $this->_get_cart_status();
        $this->json_result([
            'cart'   => $cart_status['status'],                      //返回总的购物车状态
            'amount' => $cart_status['carts'][ $store_id ]['amount']   //返回指定店铺的购物车状态
        ], 'drop_item_successed');
    }

    /**
     *    更新购物车中商品的数量，以商品为单位，AJAX更新
     *
     * @author    Garbin
     *
     * @param    none
     *
     * @return    void
     */
    function update()
    {
        $spec_id = isset( $_GET['spec_id'] ) ? intval($_GET['spec_id']) : 0;
        $quantity = isset( $_GET['quantity'] ) ? intval($_GET['quantity']) : 0;
        if ( !$spec_id || !$quantity ) {
            /* 不合法的请求 */
            return;
        }

        /* 判断库存是否足够 */
        $model_spec =& m('goodsspec');
        $spec_info = $model_spec->get($spec_id);
        if ( empty( $spec_info ) ) {
            /* 没有该规格 */
            $this->json_error('no_such_spec');

            return;
        }

        if ( $quantity > $spec_info['stock'] ) {
            /* 数量有限 */
            $this->json_error('no_enough_goods');

            return;
        }

        /* 修改数量 */
        $where = "spec_id={$spec_id} AND session_id='" . SESS_ID . "'";
        $model_cart =& m('cart');

        /* 获取购物车中的信息，用于获取价格并计算小计 */
        $cart_spec_info = $model_cart->get($where);
        if ( empty( $cart_spec_info ) ) {
            /* 并没有添加该商品到购物车 */
            return;
        }

        $store_id = $cart_spec_info['store_id'];

        /* 修改数量 */
        $model_cart->edit($where, [
            'quantity' => $quantity,
        ]);

        /* 小计 */
        $subtotal = $quantity * $cart_spec_info['price'];

        /* 返回JSON结果 */
        $cart_status = $this->_get_cart_status();
        $this->json_result([
            'cart'     => $cart_status['status'],                     //返回总的购物车状态
            'subtotal' => $subtotal,                                  //小计
            'amount'   => $cart_status['carts'][ $store_id ]['amount']  //店铺购物车总计
        ], 'update_item_successed');
    }

    /**
     *    获取购物车状态
     *
     * @author    Garbin
     * @return    array
     */
    function _get_cart_status()
    {
        /* 默认的返回格式 */
        $data = [
            'status' => [
                'quantity' => 0,      //总数量
                'amount'   => 0,      //总金额
                'kinds'    => 0,      //总种类
            ],
            'carts'  => [],    //购物车列表，包含每个购物车的状态
        ];

        /* 获取所有购物车 */
        $carts = $this->_get_carts();
        if ( empty( $carts ) ) {
            return $data;
        }
        $data['carts'] = $carts;
        foreach ( $carts as $store_id => $cart ) {
            $data['status']['quantity'] += $cart['quantity'];
            $data['status']['amount'] += $cart['amount'];
            $data['status']['kinds'] += $cart['kinds'];
        }

        return $data;
    }

    /**
     *    购物车为空
     *
     * @author    Garbin
     * @return    void
     */
    function _cart_empty()
    {
        $this->display('cart.empty.html');
    }

    /**
     *    以购物车为单位获取购物车列表及商品项
     *
     * @author    Garbin
     * @return    void
     */
    function _get_carts( $store_id = 0 )
    {
        $carts = [];

        /* 获取所有购物车中的内容 */
        $where_store_id = $store_id ? ' AND cart.store_id=' . $store_id : '';

        /* 只有是自己购物车的项目才能购买 */
        $where_user_id = $this->visitor->get('user_id') ? " AND cart.user_id=" . $this->visitor->get('user_id') : '';
        $cart_model =& m('cart');
        $cart_items = $cart_model->find([
            'conditions' => " 1=1 " . $where_store_id . $where_user_id,
            'fields'     => 'this.*,store.store_name,store.state,goodsspec.stock,goods.closed,goods.if_show',
            'join'       => 'belongs_to_store,belongs_to_goodsspec,belongs_to_goods',
        ]);
        if ( empty( $cart_items ) ) {
            return $carts;
        }
        $kinds = [];
        foreach ( $cart_items as $item ) {
            /* 小计 */
            $item['subtotal'] = $item['price'] * $item['quantity'];
            $kinds[ $item['store_id'] ][ $item['goods_id'] ] = 1;

            /* 以店铺ID为索引 */
            empty( $item['goods_image'] ) && $item['goods_image'] = Conf::get('default_goods_image');
            $carts[ $item['store_id'] ]['store_name'] = $item['store_name'];
            $carts[ $item['store_id'] ]['store_id'] = $item['store_id']; // 店铺ID
            $carts[ $item['store_id'] ]['amount'] += $item['subtotal'];   //各店铺的总金额
            $carts[ $item['store_id'] ]['quantity'] += $item['quantity'];   //各店铺的总数量

            // 商品被删除或禁售 店铺被关闭  商品显示为已下架
            if( in_array($item['if_show'],['0','2']) || in_array($item['state'],['0','2']) ){
                $item['closed'] = 1;
            }

            $carts[ $item['store_id'] ]['goods'][] = $item;
        }

        foreach ( $carts as $_store_id => $cart ) {
            $carts[ $_store_id ]['kinds'] = count(array_keys($kinds[ $_store_id ]));  //各店铺的商品种类数
        }

        return $carts;
    }

    /**
     * 获取当前用户购物车商品数量
     *
     * @param int $storeID
     *
     * @return int
     */
    public function _get_cart_count( $storeID = 0 )
    {
        $count = 0;

        $where = $storeID ? ' AND store_id=' . $storeID : '';

        $userID = $this->visitor->get('user_id');
        if ( $userID ) {
            $cartModel =& m('cart');
            $sql = "select sum(quantity) from ecm_cart where user_id = $userID $where";
            $count = $cartModel->getOne($sql);
        }

        return $count;
    }


}

?>
