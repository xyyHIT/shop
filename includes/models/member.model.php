<?php

/* 会员 member */

class MemberModel extends BaseModel
{
    var $table = 'member';
    var $prikey = 'user_id';
    var $_name = 'member';

    /* 与其它模型之间的关系 */
    var $_relation = [
        // 一个会员拥有一个店铺，id相同
        'has_store'            => [
            'model'       => 'store',       //模型的名称
            'type'        => HAS_ONE,       //关系类型
            'foreign_key' => 'store_id',    //外键名
            'dependent'   => true           //依赖
        ],
        'manage_mall'          => [
            'model'       => 'userpriv',
            'type'        => HAS_ONE,
            'foreign_key' => 'user_id',
            'ext_limit'   => [ 'store_id' => 0 ],
            'dependent'   => true
        ],
        // 一个会员拥有多个收货地址
        'has_address'          => [
            'model'       => 'address',
            'type'        => HAS_MANY,
            'foreign_key' => 'user_id',
            'dependent'   => true
        ],
        // 一个用户有多个订单
        'has_order'            => [
            'model'       => 'order',
            'type'        => HAS_MANY,
            'foreign_key' => 'buyer_id',
            'dependent'   => true
        ],
        // 一个用户有多条收到的短信
        'has_received_message' => [
            'model'       => 'message',
            'type'        => HAS_MANY,
            'foreign_key' => 'to_id',
            'dependent'   => true
        ],
        // 一个用户有多条发送出去的短信
        'has_sent_message'     => [
            'model'       => 'message',
            'type'        => HAS_MANY,
            'foreign_key' => 'from_id',
            'dependent'   => true
        ],
        // 会员和商品是多对多的关系（会员收藏商品）
        'collect_goods'        => [
            'model'        => 'goods',
            'type'         => HAS_AND_BELONGS_TO_MANY,
            'middle_table' => 'collect',    //中间表名称
            'foreign_key'  => 'user_id',
            'ext_limit'    => [ 'type' => 'goods' ],
            'reverse'      => 'be_collect', //反向关系名称
        ],
        // 会员和店铺是多对多的关系（会员收藏店铺）
        'collect_store'        => [
            'model'        => 'store',
            'type'         => HAS_AND_BELONGS_TO_MANY,
            'middle_table' => 'collect',
            'foreign_key'  => 'user_id',
            'ext_limit'    => [ 'type' => 'store' ],
            'reverse'      => 'be_collect',
        ],
        // 会员和店铺是多对多的关系（会员拥有店铺权限）
        'manage_store'         => [
            'model'        => 'store',
            'type'         => HAS_AND_BELONGS_TO_MANY,
            'middle_table' => 'user_priv',
            'foreign_key'  => 'user_id',
            'reverse'      => 'be_manage',
        ],
        // 会员和好友是多对多的关系（会员拥有多个好友）
        'has_friend'           => [
            'model'        => 'member',
            'type'         => HAS_AND_BELONGS_TO_MANY,
            'middle_table' => 'friend',
            'foreign_key'  => 'owner_id',
            'reverse'      => 'be_friend',
        ],
        // 好友是多对多的关系（会员拥有多个好友）
        'be_friend'            => [
            'model'        => 'member',
            'type'         => HAS_AND_BELONGS_TO_MANY,
            'middle_table' => 'friend',
            'foreign_key'  => 'friend_id',
            'reverse'      => 'has_friend',
        ],
        //用户与商品咨询是一对多的关系，一个会员拥有多个商品咨询
        'user_question'        => [
            'model'       => 'goodsqa',
            'type'        => HAS_MANY,
            'foreign_key' => 'user_id',
        ],
        //会员和优惠券编号是多对多的关系
        'bind_couponsn'        => [
            'model'        => 'couponsn',
            'type'         => HAS_AND_BELONGS_TO_MANY,
            'middle_table' => 'user_coupon',
            'foreign_key'  => 'user_id',
            'reverse'      => 'bind_user',
        ],
        // 会员和团购活动是多对多的关系（会员收藏商品）
        'join_groupbuy'        => [
            'model'        => 'groupbuy',
            'type'         => HAS_AND_BELONGS_TO_MANY,
            'middle_table' => 'groupbuy_log',    //中间表名称
            'foreign_key'  => 'user_id',
            'reverse'      => 'be_join', //反向关系名称
        ],
        // 一个会员发起一个团购
        'start_groupbuy'       => [
            'model'       => 'groupbuy',
            'type'        => HAS_ONE,
            'foreign_key' => 'store_id',
            'dependent'   => true
        ],
    ];

    var $_autov = [
        'user_name' => [
            'required' => true,
            'filter'   => 'trim',
        ],
        'password'  => [
            'required' => true,
            'filter'   => 'trim',
            'min'      => 6,
        ],
    ];

    /*
     * 判断名称是否唯一
     */
    function unique( $user_name, $user_id = 0 )
    {
        $conditions = "user_name = '" . $user_name . "'";
        $user_id && $conditions .= " AND user_id <> '" . $user_id . "'";

        return count($this->find([ 'conditions' => $conditions ])) == 0;
    }

    function drop( $conditions, $fields = 'portrait' )
    {
        if ( $droped_rows = parent::drop($conditions, $fields) ) {
            restore_error_handler();
            $droped_data = $this->getDroppedData();
            foreach ( $droped_data as $row ) {
                $row['portrait'] && @unlink(ROOT_PATH . '/' . $row['portrait']);
            }
            reset_error_handler();
        }

        return $droped_rows;
    }

    /**
     * 当前会员是否关注商户
     *
     * @param $userID
     * @param $storeID
     *
     * @return bool
     *
     * by Gavin
     */
    public function isFollow( $userID, $storeID )
    {
        $row = $this->db->getRow("select * from ecm_collect where type = 'store' and user_id = $userID and item_id = $storeID");
        if ( empty( $row ) ) {
            return false;
        }

        return true;
    }

}

?>