<?php

    /* 商品 */

    class GoodsApp extends StorebaseApp {
        var $_goods_mod;

        function __construct() {
            $this->GoodsApp();
        }

        function GoodsApp() {
            parent::__construct();
            $this->_goods_mod =& m('goods');
        }

        /**
         * 获取商品评价
         *
         * by Gavin 20161116
         */
        function ejComments() {
            $id = empty( $_REQUEST['id'] ) ? 0 : intval($_REQUEST['id']);
            $evaluation = empty( $_REQUEST['evaluation'] ) ? 0 : intval($_REQUEST['evaluation']);

            if ( !$id ) {
                return $this->ej_json_failed(2001);
            }

            /* 赋值商品评论 */
            $data = $this->_ej_get_goods_comment($id, 10,$evaluation);

            return $this->ej_json_success($data);
        }

        function index() {
            /* 参数 id */
            $id = empty( $_GET['id'] ) ? 0 : intval($_GET['id']);
            if ( !$id ) {
                return $this->ej_json_failed(-1);
            }

            /* 可缓存数据 */
            $data = $this->_get_common_info($id);
            $res = [];
            if ( $data === false ) {
                return $this->ej_json_failed(3001);
            } else {
                $res = $this->_assign_common_info($data);//抽取接口所需要的对应数据  by newrain
            }

            /* 更新浏览次数 */
            $this->_update_views($id);

            return $this->ej_json_success($res);
        }

        /* 商品评论 */
        function comments() {
            $id = empty( $_GET['id'] ) ? 0 : intval($_GET['id']);
            if ( !$id ) {
                $this->show_warning('Hacking Attempt');

                return;
            }

            $data = $this->_get_common_info($id);
            if ( $data === false ) {
                return;
            } else {
                $this->_assign_common_info($data);
            }

            /* 赋值商品评论 */
            $data = $this->_get_goods_comment($id, 10);
            $this->_assign_goods_comment($data);

            $this->display('goods.comments.html');
        }

        /* 销售记录 */
        function saleslog() {
            $id = empty( $_GET['id'] ) ? 0 : intval($_GET['id']);
            if ( !$id ) {
                $this->show_warning('Hacking Attempt');

                return;
            }

            $data = $this->_get_common_info($id);
            if ( $data === false ) {
                return;
            } else {
                $this->_assign_common_info($data);
            }

            /* 赋值销售记录 */
            $data = $this->_get_sales_log($id, 10);
            $this->_assign_sales_log($data);

            $this->display('goods.saleslog.html');
        }

        function qa() {
            $goods_qa =& m('goodsqa');
            $id = intval($_GET['id']);
            if ( !$id ) {
                $this->show_warning('Hacking Attempt');

                return;
            }
            if ( !IS_POST ) {
                $data = $this->_get_common_info($id);
                if ( $data === false ) {
                    return;
                } else {
                    $this->_assign_common_info($data);
                }
                $data = $this->_get_goods_qa($id, 10);
                $this->_assign_goods_qa($data);

                //是否开启验证码
                if ( Conf::get('captcha_status.goodsqa') ) {
                    $this->assign('captcha', 1);
                }
                $this->assign('guest_comment_enable', Conf::get('guest_comment'));
                /*赋值产品咨询*/
                $this->display('goods.qa.html');
            } else {
                /* 不允许游客评论 */
                if ( !Conf::get('guest_comment') && !$this->visitor->has_login ) {
                    $this->show_warning('guest_comment_disabled');

                    return;
                }
                $content = ( isset( $_POST['content'] ) ) ? trim($_POST['content']) : '';
                //$type = (isset($_POST['type'])) ? trim($_POST['type']) : '';
                $email = ( isset( $_POST['email'] ) ) ? trim($_POST['email']) : '';
                $hide_name = ( isset( $_POST['hide_name'] ) ) ? trim($_POST['hide_name']) : '';
                if ( empty( $content ) ) {
                    $this->show_warning('content_not_null');

                    return;
                }
                //对验证码和邮件进行判断

                if ( Conf::get('captcha_status.goodsqa') ) {
                    if ( base64_decode($_SESSION['captcha']) != strtolower($_POST['captcha']) ) {
                        $this->show_warning('captcha_failed');

                        return;
                    }
                }
                if ( !empty( $email ) && !is_email($email) ) {
                    $this->show_warning('email_not_correct');

                    return;
                }
                $user_id = empty( $hide_name ) ? $_SESSION['user_info']['user_id'] : 0;
                $conditions = 'g.goods_id =' . $id;
                $goods_mod = &m('goods');
                $ids = $goods_mod->get([
                    'fields'     => 'store_id,goods_name',
                    'conditions' => $conditions
                ]);
                extract($ids);
                $data = [
                    'question_content' => $content,
                    'type'             => 'goods',
                    'item_id'          => $id,
                    'item_name'        => addslashes($goods_name),
                    'store_id'         => $store_id,
                    'email'            => $email,
                    'user_id'          => $user_id,
                    'time_post'        => gmtime(),
                ];
                if ( $goods_qa->add($data) ) {
                    header("Location: index.php?app=goods&act=qa&id={$id}#module\n");
                    exit;
                } else {
                    $this->show_warning('post_fail');
                    exit;
                }
            }
        }

        /**
         * 取得公共信息
         *
         * @param   int $id
         *
         * @return  false   失败
         *          array   成功
         */
        function _get_common_info( $id ) {
            $cache_server =& cache_server();
            $key = 'page_of_goods_' . $id;
            $data = $cache_server->get($key);
            $cached = true;
            if ( $data === false ) {
                $cached = false;
                $data = [ 'id' => $id ];

                /* 商品信息 */
                $goods = $this->_goods_mod->get_info($id);
                if ( $goods['state'] == 2 ) {
                    $this->show_warning('the_store_is_closed');
                    exit;
                }
                if ( !$goods || $goods['if_show'] == 0 || $goods['closed'] == 1 || $goods['state'] != 1 ) {
                    $this->show_warning('goods_not_exist');

                    return false;
                }
                $goods['tags'] = $goods['tags'] ? explode(',', trim($goods['tags'], ',')) : [];

                $data['goods'] = $goods;

                /* 店铺信息 */
                if ( !$goods['store_id'] ) {
                    $this->show_warning('store of goods is empty');

                    return false;
                }
                $this->set_store($goods['store_id']);
                $data['store_data'] = $this->get_store_data();

                /* 当前位置 */
                $data['cur_local'] = $this->_get_curlocal($goods['cate_id']);
                $data['goods']['related_info'] = $this->_get_related_objects($data['goods']['tags']);
                /* 分享链接 */
                $data['share'] = $this->_get_share($goods);

                $cache_server->set($key, $data, 1800);
            }
            if ( $cached ) {
                $this->set_store($data['goods']['store_id']);
            }

            return $data;
        }

        function _get_related_objects( $tags ) {
            if ( empty( $tags ) ) {
                return [];
            }
            $tag = $tags[ array_rand($tags) ];
            $ms =& ms();

            return $ms->tag_get($tag);
        }

        /* 赋值公共信息*/
        function _assign_common_info( $data ) {
            /* 商品信息 */
            $goods = $data['goods'];
            //print_r($goods);
            $result['goods']['goods_id'] = $data['goods']['goods_id'];
            $result['goods']['store_id'] = $data['goods']['store_id'];
            $result['goods']['cate_id'] = $data['goods']['cate_id'];
            $result['goods']['goods_name'] = $data['goods']['goods_name'];
            $result['goods']['collects'] = $data['goods']['collects'];
            $result['goods']['views'] = $data['goods']['views'];
            $result['goods']['default_image'] = $data['goods']['default_image'];//商品图片地址
            $result['goods']['price'] = $data['goods']['price'];
            $result['goods']['sales'] = $data['goods']['sales'];//商品被售出的数目
            $result['goods']['good_comment'] = '90%';//好评量，目前先放假数据，待评价完成  继续编写
            $result['goods']['ship_price'] = '10.00';//单件商品的运费，为满足平台需求，后续用真实数据替换假数据
            $result['goods']['vediourl'] = 'http://www.baidu.com';//视频地址
            $result['goods']['detail_image'] = 'http://www.baidu.com';//详情图片地址   用假数据，后续用真实替换
            $result['goods']['detail_desc'] = $data['goods']['description'];//详情文字描述
            $result['goods']['commend_goods'] = $this->_get_ejrecommended_goods($data['goods']['store_id'], 6);//店铺精品推荐   默认显示六个
            $result['store']['logo'] = $data['store_data']['store_logo'];
            $result['store']['sgrade'] = $data['store_data']['sgrade'];//店铺等级
            $result['store']['name'] = $data['store_data']['store_name'];
            $result['store']['goods_count'] = $data['store_data']['goods_count'];
            $result['store']['collect_count'] = $this->_ejget_collect_num('store', $data['goods']['store_id']);
            $result['store']['tel'] = $data['store_data']['tel'];//店铺联系电话（和卖家的区分）
			$colstore =  $this->_goods_mod->db->getOne("select user_id from ".DB_PREFIX."collect where type=store and item_id=".$data['goods']['store_id']." and user_id=".OPENID);
			$result['store']['collectsign'] = empty($colstore)?'0':'1';
			$colsgoods =  $this->_goods_mod->db->getOne("select user_id from ".DB_PREFIX."collect where type=goods and item_id=".$data['goods']['goods_id']." and user_id=".OPENID);
			$result['goods']['collectsign'] = empty($colsgoods)?'0':'1'; 			
			return $result;
        }

        /* 取得浏览历史 */
        function _get_goods_history( $id, $num = 9 ) {
            $goods_list = [];
            $goods_ids = ecm_getcookie('goodsBrowseHistory');
            $goods_ids = $goods_ids ? explode(',', $goods_ids) : [];
            if ( $goods_ids ) {
                $rows = $this->_goods_mod->find([
                    'conditions' => $goods_ids,
                    'fields'     => 'goods_name,default_image',
                ]);
                foreach ( $goods_ids as $goods_id ) {
                    if ( isset( $rows[ $goods_id ] ) ) {
                        empty( $rows[ $goods_id ]['default_image'] ) && $rows[ $goods_id ]['default_image'] = Conf::get('default_goods_image');
                        $goods_list[] = $rows[ $goods_id ];
                    }
                }
            }
            $goods_ids[] = $id;
            if ( count($goods_ids) > $num ) {
                unset( $goods_ids[0] );
            }
            ecm_setcookie('goodsBrowseHistory', join(',', array_unique($goods_ids)));

            return $goods_list;
        }

        /* 取得销售记录 */
        function _get_sales_log( $goods_id, $num_per_page ) {
            $data = [];

            $page = $this->_get_page($num_per_page);
            $order_goods_mod =& m('ordergoods');
            $sales_list = $order_goods_mod->find([
                'conditions' => "goods_id = '$goods_id' AND status = '" . ORDER_FINISHED . "'",
                'join'       => 'belongs_to_order',
                'fields'     => 'buyer_id, buyer_name, add_time, anonymous, goods_id, specification, price, quantity, evaluation',
                'count'      => true,
                'order'      => 'add_time desc',
                'limit'      => $page['limit'],
            ]);
            $data['sales_list'] = $sales_list;

            $page['item_count'] = $order_goods_mod->getCount();
            $this->_format_page($page);
            $data['page_info'] = $page;
            $data['more_sales'] = $page['item_count'] > $num_per_page;

            return $data;
        }

        /* 赋值销售记录 */
        function _assign_sales_log( $data ) {
            $this->assign('sales_list', $data['sales_list']);
            $this->assign('page_info', $data['page_info']);
            $this->assign('more_sales', $data['more_sales']);
        }

        /* 取得商品评论 */
        function _get_goods_comment( $goods_id, $num_per_page ) {
            $data = [];

            $page = $this->_get_page($num_per_page);
            $order_goods_mod =& m('ordergoods');
            $comments = $order_goods_mod->find([
                'conditions' => "goods_id = '$goods_id' AND evaluation_status = '1'",
                'join'       => 'belongs_to_order',
                'fields'     => 'buyer_id, buyer_name, anonymous, evaluation_time, comment, evaluation',
                'count'      => true,
                'order'      => 'evaluation_time desc',
                'limit'      => $page['limit'],
            ]);
            $data['comments'] = $comments;

            $page['item_count'] = $order_goods_mod->getCount();
            $this->_format_page($page);
            $data['page_info'] = $page;
            $data['more_comments'] = $page['item_count'] > $num_per_page;

            return $data;
        }

        /**
         * 艺加 - 获取商品评论
         *
         * @param     $goods_id
         * @param     $num_per_page
         * @param int $evaluation
         *
         * @return array
         */
        function _ej_get_goods_comment( $goods_id, $num_per_page,$evaluation = 0) {
            $condition = '';
            $evaluation && in_array(intval($evaluation),[1,2,3]) && $condition = ' AND evaluation='.$evaluation;

            $data = [];

            $page = $this->_get_page($num_per_page);
            $order_goods_mod =& m('ordergoods');
            $comments = $order_goods_mod->find([
                'conditions' => "goods_id = '$goods_id' AND evaluation_status = '1'" . $condition,
                'join'       => 'belongs_to_order',
                'fields'     => 'buyer_id, buyer_name, anonymous, evaluation_time, comment, evaluation, is_reply,reply',
                'count'      => true,
                'order'      => 'evaluation_time desc',
                'limit'      => $page['limit'],
            ]);
            $data['comments'] = $comments;

            $page['item_count'] = $order_goods_mod->getCount();
            $data['page_info'] = $page;

            return $data;
        }

        /* 赋值商品评论 */
        function _assign_goods_comment( $data ) {
            $this->assign('goods_comments', $data['comments']);
            $this->assign('page_info', $data['page_info']);
            $this->assign('more_comments', $data['more_comments']);
        }

        /* 取得商品咨询 */
        function _get_goods_qa( $goods_id, $num_per_page ) {
            $page = $this->_get_page($num_per_page);
            $goods_qa = &m('goodsqa');
            $qa_info = $goods_qa->find([
                'join'       => 'belongs_to_user',
                'fields'     => 'member.user_name,question_content,reply_content,time_post,time_reply',
                'conditions' => '1 = 1 AND item_id = ' . $goods_id . " AND type = 'goods'",
                'limit'      => $page['limit'],
                'order'      => 'time_post desc',
                'count'      => true
            ]);
            $page['item_count'] = $goods_qa->getCount();
            $this->_format_page($page);

            //如果登陆，则查出email
            if ( !empty( $_SESSION['user_info'] ) ) {
                $user_mod = &m('member');
                $user_info = $user_mod->get([
                    'fields'     => 'email',
                    'conditions' => '1=1 AND user_id = ' . $_SESSION['user_info']['user_id']
                ]);
                extract($user_info);
            }

            return [
                'email'     => $email,
                'page_info' => $page,
                'qa_info'   => $qa_info,
            ];
        }

        /* 赋值商品咨询 */
        function _assign_goods_qa( $data ) {
            $this->assign('email', $data['email']);
            $this->assign('page_info', $data['page_info']);
            $this->assign('qa_info', $data['qa_info']);
        }

        /* 更新浏览次数 */
        function _update_views( $id ) {
            $goodsstat_mod =& m('goodsstatistics');
            $goodsstat_mod->edit($id, "views = views + 1");
        }

        /**
         * 取得当前位置
         *
         * @param int $cate_id 分类id
         */
        function _get_curlocal( $cate_id ) {
            $parents = [];
            if ( $cate_id ) {
                $gcategory_mod =& bm('gcategory');
                $parents = $gcategory_mod->get_ancestor($cate_id, true);
            }

            $curlocal = [
                [ 'text' => LANG::get('all_categories'), 'url' => url('app=category') ],
            ];
            foreach ( $parents as $category ) {
                $curlocal[] = [ 'text' => $category['cate_name'], 'url' => url('app=search&cate_id=' . $category['cate_id']) ];
            }
            $curlocal[] = [ 'text' => LANG::get('goods_detail') ];

            return $curlocal;
        }

        function _get_share( $goods ) {
            $m_share = &af('share');
            $shares = $m_share->getAll();
            $shares = array_msort($shares, [ 'sort_order' => SORT_ASC ]);
            $goods_name = ecm_iconv(CHARSET, 'utf-8', $goods['goods_name']);
            $goods_url = urlencode(SITE_URL . '/' . str_replace('&amp;', '&', url('app=goods&id=' . $goods['goods_id'])));
            $site_title = ecm_iconv(CHARSET, 'utf-8', Conf::get('site_title'));
            $share_title = urlencode($goods_name . '-' . $site_title);
            foreach ( $shares as $share_id => $share ) {
                $shares[ $share_id ]['link'] = str_replace(
                    [ '{$link}', '{$title}' ],
                    [ $goods_url, $share_title ],
                    $share['link']);
            }

            return $shares;
        }

        function _get_seo_info( $data ) {
            $seo_info = $keywords = [];
            $seo_info['title'] = $data['goods_name'] . ' - ' . Conf::get('site_title');
            $keywords = [
                $data['brand'],
                $data['goods_name'],
                $data['cate_name']
            ];
            $seo_info['keywords'] = implode(',', array_merge($keywords, $data['tags']));
            $seo_info['description'] = sub_str(strip_tags($data['description']), 10, true);

            return $seo_info;
        }

        /* 取得推荐商品 by newrain*/
        function _get_ejrecommended_goods( $id, $num = 6 ) {
            $goods_mod =& bm('goods', [ '_store_id' => $id ]);
            $goods_list = $goods_mod->find([
                'conditions' => "closed = 0 AND if_show = 1 AND recommended = 1",
                'fields'     => 'goods_name, default_image, price',
                'limit'      => $num,
            ]);
            foreach ( $goods_list as $key => $goods ) {
                empty( $goods['default_image'] ) && $goods_list[ $key ]['default_image'] = Conf::get('default_goods_image');
            }

            return $goods_list;
        }

        /*添加用户关注方法 by newrain*/
        function _ejget_collect_num( $type = 'store', $id ) {
            $model_store =& m('store');
            $collect_store = $model_store->getOne('select count(*) from ' . DB_PREFIX . "collect where type = '" . $type . "' and item_id=" . $id);

            return empty( $collect_store ) ? 0 : intval($collect_store);
        }
    }

?>
