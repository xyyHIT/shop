<?php

/**
 *    前台控制器基础类
 *
 * @author    Garbin
 * @usage     none
 */
class FrontendApp extends ECBaseApp
{
    function __construct()
    {
        $this->FrontendApp();

        //用户保持常登录状态 调试时才会使用
        if ( !$this->visitor->has_login ) {
            if ( defined('IS_WECHAT') && defined('USER_ID') && !IS_WECHAT && USER_ID ) {
                $this->_do_login(USER_ID);
            }
        }
        if ( !in_array(strtolower(APP), [ 'mlselection' ]) ) {
            if ( IS_WECHAT ) {
                if ( empty( $_SESSION['wx_openid'] ) ) {
                    if ( strtolower(APP) === 'wechat' && strtolower(ACT) == 'redirecthtml' ) {
                        // 删除无用参数
                        $get = $_GET;
                        $modul = $get['modul'];
                        $action = $get['action'];
                        unset( $get['app'] );
                        unset( $get['act'] );
                        unset( $get['modul'] );
                        unset( $get['action'] );

                        // 生成url query
                        $query = http_build_query($get);

                        /**
                         * 从微信获取openid
                         */
                        $redirectUrl = "shop/html/$modul/$action.html"; // 微信回调地址
                        $query && $redirectUrl .= '?' . $query;
                        $_SESSION['wx_target_url'] = $redirectUrl;
                        Wechat::handler()->oauth->redirect()->send(); // 重定向微信api
                        exit();// 不再执行以下代码
                    } else if ( !( strtolower(APP) === 'wechat' && in_array(strtolower(ACT), [ 'oauthcallback', 'notify' ]) ) ) {
                        $this->ej_json_failed(3003); // 刷新当前页面
                        exit();
                    }
                } else {
                    // 拍卖使用cookie  ** 很重要,勿动 !!! ** by Gavin 20161209
                    empty( $_COOKIE['PLATWXUSER'] ) && ecm_setrawcookie('PLATWXUSER', $_SESSION['wx_openid'] . '#YJPAI', time() + 3600);

                    /**
                     * 重新登录并操作(添加购物车,喜欢商品,关注店铺)
                     *
                     * 这里进行登录,并重定向到商品页
                     */
                    strtolower(APP) === 'wechat' && strtolower(ACT) == 'redirectbusiness' && $this->checkLoginIdentity();

                    /**
                     * 访问特定html时,需登录才可访问
                     *
                     * 这里进行登录并重定向
                     */
                    strtolower(APP) === 'wechat' && strtolower(ACT) == 'redirecthtml'
                    && in_array(strtolower($_GET['modul']), [ 'my', 'order', 'cart' ]) && $this->checkLoginIdentity();
                }

            }
        }

    }

    function FrontendApp()
    {
        Lang::load(lang_file('common'));
        Lang::load(lang_file(APP));
        parent::__construct();

        // 判断商城是否关闭
        if ( !Conf::get('site_status') ) {
            $this->show_warning(Conf::get('closed_reason'));
            exit;
        }
    }

    function _config_view()
    {
        parent::_config_view();
        $this->_view->template_dir = ROOT_PATH . '/themes';
        $this->_view->compile_dir = ROOT_PATH . '/temp/compiled/mall';
        $this->_view->res_base = SITE_URL . '/themes';
        $this->_config_seo([
            'title'       => Conf::get('site_title'),
            'description' => Conf::get('site_description'),
            'keywords'    => Conf::get('site_keywords')
        ]);
    }

    function display( $tpl )
    {
        $cart =& m('cart');
        $this->assign('cart_goods_kinds', $cart->get_kinds(SESS_ID, $this->visitor->get('user_id')));
        /* 新消息 */
        $this->assign('new_message', isset( $this->visitor ) ? $this->_get_new_message() : '');
        $this->assign('navs', $this->_get_navs());  // 自定义导航
        $this->assign('acc_help', ACC_HELP);        // 帮助中心分类code
        $this->assign('site_title', Conf::get('site_title'));
        $this->assign('site_logo', Conf::get('site_logo'));
        $this->assign('statistics_code', Conf::get('statistics_code')); // 统计代码
        $current_url = explode('/', $_SERVER['REQUEST_URI']);
        $count = count($current_url);
        $this->assign('current_url', $count > 1 ? $current_url[ $count - 1 ] : $_SERVER['REQUEST_URI']);// 用于设置导航状态(以后可能会有问题)
        parent::display($tpl);
    }

    function login()
    {
        if ( $this->visitor->has_login ) {
            $this->show_warning('has_login');

            return;
        }
        if ( !IS_POST ) {
            if ( !empty( $_GET['ret_url'] ) ) {
                $ret_url = trim($_GET['ret_url']);
            } else {
                if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
                    $ret_url = $_SERVER['HTTP_REFERER'];
                } else {
                    $ret_url = SITE_URL . '/index.php';
                }
            }
            /* 防止登陆成功后跳转到登陆、退出的页面 */
            $ret_url = strtolower($ret_url);
            if ( str_replace([ 'act=login', 'act=logout', ], '', $ret_url) != $ret_url ) {
                $ret_url = SITE_URL . '/index.php';
            }

            if ( Conf::get('captcha_status.login') ) {
                $this->assign('captcha', 1);
            }
            $this->import_resource([ 'script' => 'jquery.plugins/jquery.validate.js' ]);
            $this->assign('ret_url', rawurlencode($ret_url));
            $this->_curlocal(LANG::get('user_login'));
            $this->_config_seo('title', Lang::get('user_login') . ' - ' . Conf::get('site_title'));
            $this->display('login.html');
            /* 同步退出外部系统 */
            if ( !empty( $_GET['synlogout'] ) ) {
                $ms =& ms();
                echo $synlogout = $ms->user->synlogout();
            }
        } else {
            if ( Conf::get('captcha_status.login') && base64_decode($_SESSION['captcha']) != strtolower($_POST['captcha']) ) {
                $this->show_warning('captcha_failed');

                return;
            }

            $user_name = trim($_POST['user_name']);
            $password = $_POST['password'];

            $ms =& ms();
            $user_id = $ms->user->auth($user_name, $password);
            if ( !$user_id ) {
                /* 未通过验证，提示错误信息 */
                $this->show_warning($ms->user->get_error());

                return;
            } else {
                /* 通过验证，执行登陆操作 */
                $this->_do_login($user_id);

                /* 同步登陆外部系统 */
                $synlogin = $ms->user->synlogin($user_id);
            }

            $this->show_message(Lang::get('login_successed') . $synlogin,
                'back_before_login', rawurldecode($_POST['ret_url']),
                'enter_member_center', 'index.php?app=member'
            );
        }
    }

    function pop_warning( $msg, $dialog_id = '', $url = '' )
    {
        if ( $msg == 'ok' ) {
            if ( empty( $dialog_id ) ) {
                $dialog_id = APP . '_' . ACT;
            }
            if ( !empty( $url ) ) {
                echo "<script type='text/javascript'>window.parent.location.href='" . $url . "';</script>";
            } else {
                echo "<script type='text/javascript'>window.parent.js_success('" . $dialog_id . "');</script>";
            }
        } else {
            header("Content-Type:text/html;charset=" . CHARSET);
            $msg = is_array($msg) ? $msg : [ [ 'msg' => $msg ] ];
            $errors = '';
            foreach ( $msg as $k => $v ) {
                $error = $v[ obj ] ? Lang::get($v[ msg ]) . " [" . Lang::get($v[ obj ]) . "]" : Lang::get($v[ msg ]);
                $errors .= $errors ? "<br />" . $error : $error;
            }
            echo "<script type='text/javascript'>window.parent.js_fail('" . $errors . "');</script>";
        }
    }

    function logout()
    {
        $this->visitor->logout();

        /* 跳转到登录页，执行同步退出操作 */
        header("Location: index.php?app=member&act=login&synlogout=1");

        return;
    }

    /* 执行登录动作 */
    function _do_login( $user_id )
    {
        $mod_user =& m('member');

        $user_info = $mod_user->get([
            'conditions' => "user_id = '{$user_id}'",
            'join'       => 'has_store',                 //关联查找看看是否有店铺
            'fields'     => 'user_id, user_name, reg_time, last_login, last_ip, store_id',
        ]);

        /* 店铺ID */
        $my_store = empty( $user_info['store_id'] ) ? 0 : $user_info['store_id'];

        /* 保证基础数据整洁 */
        //unset($user_info['store_id']);

        /* 分派身份 */
        $this->visitor->assign($user_info);

        /* 更新用户登录信息 */
        $mod_user->edit("user_id = '{$user_id}'", "last_login = '" . gmtime() . "', last_ip = '" . real_ip() . "', logins = logins + 1");

        /* 更新购物车中的数据 */
        $mod_cart =& m('cart');
        $mod_cart->edit("(user_id = '{$user_id}' OR session_id = '" . SESS_ID . "') AND store_id <> '{$my_store}'", [
            'user_id'    => $user_id,
            'session_id' => SESS_ID,
        ]);

        /* 去掉重复的项 */
        $cart_items = $mod_cart->find([
            'conditions' => "user_id='{$user_id}' GROUP BY spec_id",
            'fields'     => 'COUNT(spec_id) as spec_count, spec_id, rec_id',
        ]);
        if ( !empty( $cart_items ) ) {
            foreach ( $cart_items as $rec_id => $cart_item ) {
                if ( $cart_item['spec_count'] > 1 ) {
                    $mod_cart->drop("user_id='{$user_id}' AND spec_id='{$cart_item['spec_id']}' AND rec_id <> {$cart_item['rec_id']}");
                }
            }
        }
    }

    /* 取得导航 */
    function _get_navs()
    {
        $cache_server =& cache_server();
        $key = 'common.navigation';
        $data = $cache_server->get($key);
        if ( $data === false ) {
            $data = [
                'header' => [],
                'middle' => [],
                'footer' => [],
            ];
            $nav_mod =& m('navigation');
            $rows = $nav_mod->find([
                'order' => 'type, sort_order',
            ]);
            foreach ( $rows as $row ) {
                $data[ $row['type'] ][] = $row;
            }
            $cache_server->set($key, $data, 86400);
        }

        return $data;
    }

    /**
     *    获取JS语言项
     *
     * @author    Garbin
     *
     * @param    none
     *
     * @return    void
     */
    function jslang( $lang = '' )
    {
        $lang = Lang::fetch(lang_file('jslang'));
        parent::jslang($lang);
    }

    /**
     *    视图回调函数[显示小挂件]
     *
     * @author    Garbin
     *
     * @param     array $options
     *
     * @return    void
     */
    function display_widgets( $options )
    {
        $area = isset( $options['area'] ) ? $options['area'] : '';
        $page = isset( $options['page'] ) ? $options['page'] : '';
        if ( !$area || !$page ) {
            return;
        }
        include_once( ROOT_PATH . '/includes/widget.base.php' );

        /* 获取该页面的挂件配置信息 */
        $widgets = get_widget_config($this->_get_template_name(), $page);

        /* 如果没有该区域 */
        if ( !isset( $widgets['config'][ $area ] ) ) {
            return;
        }

        /* 将该区域内的挂件依次显示出来 */
        foreach ( $widgets['config'][ $area ] as $widget_id ) {
            $widget_info = $widgets['widgets'][ $widget_id ];
            $wn = $widget_info['name'];
            $options = $widget_info['options'];

            $widget =& widget($widget_id, $wn, $options);
            $widget->display();
        }
    }

    /**
     *    获取当前使用的模板名称
     *
     * @author    Garbin
     * @return    string
     */
    function _get_template_name()
    {
        return 'default';
    }

    /**
     *    获取当前使用的风格名称
     *
     * @author    Garbin
     * @return    string
     */
    function _get_style_name()
    {
        return 'default';
    }

    /**
     *    当前位置
     *
     * @author    Garbin
     *
     * @param    none
     *
     * @return    void
     */
    function _curlocal( $arr )
    {
        $curlocal = [ [
            'text' => Lang::get('index'),
            'url'  => SITE_URL . '/index.php',
        ] ];
        if ( is_array($arr) ) {
            $curlocal = array_merge($curlocal, $arr);
        } else {
            $args = func_get_args();
            if ( !empty( $args ) ) {
                $len = count($args);
                for ( $i = 0; $i < $len; $i += 2 ) {
                    $curlocal[] = [
                        'text' => $args[ $i ],
                        'url'  => $args[ $i + 1 ],
                    ];
                }
            }
        }

        $this->assign('_curlocal', $curlocal);
    }

    function _init_visitor()
    {
        $this->visitor =& env('visitor', new UserVisitor());
    }
}

/**
 *    前台访问者
 *
 * @author    Garbin
 * @usage     none
 */
class UserVisitor extends BaseVisitor
{
    var $_info_key = 'user_info';

    /**
     *    退出登录
     *
     * @author    Garbin
     *
     * @param    none
     *
     * @return    void
     */
    function logout()
    {
        /* 将购物车中的相关项的session_id置为空 */
        $mod_cart =& m('cart');
        $mod_cart->edit("user_id = '" . $this->get('user_id') . "'", [
            'session_id' => '',
        ]);

        /* 退出登录 */
        parent::logout();
    }
}

/**
 *    商城控制器基类
 *
 * @author    Garbin
 * @usage     none
 */
class MallbaseApp extends FrontendApp
{
    function _run_action()
    {
        /* 只有登录的用户才可访问 */
        if ( !$this->visitor->has_login && in_array(APP, [ 'apply' ]) ) {
            header('Location: index.php?app=member&act=login&ret_url=' . rawurlencode($_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']));

            return;
        }

        parent::_run_action();
    }

    function _config_view()
    {
        parent::_config_view();

        $template_name = $this->_get_template_name();
        $style_name = $this->_get_style_name();

//        $this->_view->template_dir = ROOT_PATH . "/themes/mall/{$template_name}";
        $this->_view->template_dir = ROOT_PATH . "/shop/html";
        $this->_view->compile_dir = ROOT_PATH . "/temp/compiled/mall/{$template_name}";
        $this->_view->res_base = SITE_URL . "/themes/mall/{$template_name}/styles/{$style_name}";
    }

    /* 取得支付方式实例 */
    function _get_payment( $code, $payment_info )
    {
        include_once( ROOT_PATH . '/includes/payment.base.php' );
        include( ROOT_PATH . '/includes/payments/' . $code . '/' . $code . '.payment.php' );
        $class_name = ucfirst($code) . 'Payment';

        return new $class_name($payment_info);
    }

    /**
     *   获取当前所使用的模板名称
     *
     * @author    Garbin
     * @return    string
     */
    function _get_template_name()
    {
        $template_name = Conf::get('template_name');
        if ( !$template_name ) {
            $template_name = 'default';
        }

        return $template_name;
    }

    /**
     *    获取当前模板中所使用的风格名称
     *
     * @author    Garbin
     * @return    string
     */
    function _get_style_name()
    {
        $style_name = Conf::get('style_name');
        if ( !$style_name ) {
            $style_name = 'default';
        }

        return $style_name;
    }
}

/**
 *    购物流程子系统基础类
 *
 * @author    Garbin
 * @usage     none
 */
class ShoppingbaseApp extends MallbaseApp
{
    function _run_action()
    {
        /* 只有登录的用户才可访问 */
        if ( !$this->visitor->has_login && !in_array(ACT, [ 'login', 'register', 'check_user' ]) ) {
            if ( !IS_AJAX && !IS_WECHAT ) {
                // header('Location:index.php?app=member&act=login&ret_url=' . rawurlencode($_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']));
                // return;
            } else {
                $this->ej_json_failed(3004);
                exit();
            }
        }

        parent::_run_action();
    }
}

/**
 *    用户中心子系统基础类
 *
 * @author    Garbin
 * @usage     none
 */
class MemberbaseApp extends MallbaseApp
{
    function _run_action()
    {
        /* 只有登录的用户才可访问 */
        if ( !$this->visitor->has_login ) {
            $app = strtolower(APP);
            $act = strtolower(ACT);
            if ( ( $app == 'my_favorite' && $act == 'index' ) || ( $app == 'my_favorite' && $act == 'add' ) ) {
                // 商品详情中  需要登录重定向业务页
                $this->ej_json_failed(3005);
                exit();
            }

            $this->ej_json_failed(3004);
            exit();
        }

        parent::_run_action();
    }

    /**
     *    当前选中的菜单项
     *
     * @author    Garbin
     *
     * @param     string $item
     *
     * @return    void
     */
    function _curitem( $item )
    {
        $this->assign('has_store', $this->visitor->get('has_store'));
        $this->assign('_member_menu', $this->_get_member_menu());
        $this->assign('_curitem', $item);
    }

    /**
     *    当前选中的子菜单
     *
     * @author    Garbin
     *
     * @param     string $item
     *
     * @return    void
     */
    function _curmenu( $item )
    {
        $_member_submenu = $this->_get_member_submenu();
        foreach ( $_member_submenu as $key => $value ) {
            $_member_submenu[ $key ]['text'] = $value['text'] ? $value['text'] : Lang::get($value['name']);
        }
        $this->assign('_member_submenu', $_member_submenu);
        $this->assign('_curmenu', $item);
    }

    /**
     *    获取子菜单列表
     *
     * @author    Garbin
     *
     * @param    none
     *
     * @return    void
     */
    function _get_member_submenu()
    {
        return [];
    }

    /**
     *    获取用户中心全局菜单列表
     *
     * @author    Garbin
     *
     * @param    none
     *
     * @return    void
     */
    function _get_member_menu()
    {
        $menu = [];

        /* 我的ECMall */
        $menu['my_ecmall'] = [
            'name'    => 'my_ecmall',
            'text'    => Lang::get('my_ecmall'),
            'submenu' => [
                'overview'   => [
                    'text' => Lang::get('overview'),
                    'url'  => 'index.php?app=member',
                    'name' => 'overview',
                    'icon' => 'ico1',
                ],
                'my_profile' => [
                    'text' => Lang::get('my_profile'),
                    'url'  => 'index.php?app=member&act=profile',
                    'name' => 'my_profile',
                    'icon' => 'ico2',
                ],
                'message'    => [
                    'text' => Lang::get('message'),
                    'url'  => 'index.php?app=message&act=newpm',
                    'name' => 'message',
                    'icon' => 'ico3',
                ],
                'friend'     => [
                    'text' => Lang::get('friend'),
                    'url'  => 'index.php?app=friend',
                    'name' => 'friend',
                    'icon' => 'ico4',
                ],
                /*
                'my_credit'  => array(
                    'text'  => Lang::get('my_credit'),
                    'url'   => 'index.php?app=member&act=credit',
                    'name'  => 'my_credit',
                ),*/
            ],
        ];


        /* 我是买家 */
        $menu['im_buyer'] = [
            'name'    => 'im_buyer',
            'text'    => Lang::get('im_buyer'),
            'submenu' => [
                'my_order'    => [
                    'text' => Lang::get('my_order'),
                    'url'  => 'index.php?app=buyer_order',
                    'name' => 'my_order',
                    'icon' => 'ico5',
                ],
                'my_groupbuy' => [
                    'text' => Lang::get('my_groupbuy'),
                    'url'  => 'index.php?app=buyer_groupbuy',
                    'name' => 'my_groupbuy',
                    'icon' => 'ico21',
                ],
                'my_question' => [
                    'text' => Lang::get('my_question'),
                    'url'  => 'index.php?app=my_question',
                    'name' => 'my_question',
                    'icon' => 'ico17',

                ],
                'my_favorite' => [
                    'text' => Lang::get('my_favorite'),
                    'url'  => 'index.php?app=my_favorite',
                    'name' => 'my_favorite',
                    'icon' => 'ico6',
                ],
                'my_address'  => [
                    'text' => Lang::get('my_address'),
                    'url'  => 'index.php?app=my_address',
                    'name' => 'my_address',
                    'icon' => 'ico7',
                ],
                'my_coupon'   => [
                    'text' => Lang::get('my_coupon'),
                    'url'  => 'index.php?app=my_coupon',
                    'name' => 'my_coupon',
                    'icon' => 'ico20',
                ],
            ],
        ];

        if ( !$this->visitor->get('has_store') && Conf::get('store_allow') ) {
            /* 没有拥有店铺，且开放申请，则显示申请开店链接 */
            /*$menu['im_seller'] = array(
                'name'  => 'im_seller',
                'text'  => Lang::get('im_seller'),
                'submenu'   => array(),
            );

            $menu['im_seller']['submenu']['overview'] = array(
                'text'  => Lang::get('apply_store'),
                'url'   => 'index.php?app=apply',
                'name'  => 'apply_store',
            );*/
            $menu['overview'] = [
                'text' => Lang::get('apply_store'),
                'url'  => 'index.php?app=apply',
            ];
        }
        if ( $this->visitor->get('manage_store') ) {
            /* 指定了要管理的店铺 */
            $menu['im_seller'] = [
                'name'    => 'im_seller',
                'text'    => Lang::get('im_seller'),
                'submenu' => [],
            ];

            $menu['im_seller']['submenu']['my_goods'] = [
                'text' => Lang::get('my_goods'),
                'url'  => 'index.php?app=my_goods',
                'name' => 'my_goods',
                'icon' => 'ico8',
            ];
            $menu['im_seller']['submenu']['groupbuy_manage'] = [
                'text' => Lang::get('groupbuy_manage'),
                'url'  => 'index.php?app=seller_groupbuy',
                'name' => 'groupbuy_manage',
                'icon' => 'ico22',
            ];
            $menu['im_seller']['submenu']['my_qa'] = [
                'text' => Lang::get('my_qa'),
                'url'  => 'index.php?app=my_qa',
                'name' => 'my_qa',
                'icon' => 'ico18',
            ];
            $menu['im_seller']['submenu']['my_category'] = [
                'text' => Lang::get('my_category'),
                'url'  => 'index.php?app=my_category',
                'name' => 'my_category',
                'icon' => 'ico9',
            ];
            $menu['im_seller']['submenu']['order_manage'] = [
                'text' => Lang::get('order_manage'),
                'url'  => 'index.php?app=seller_order',
                'name' => 'order_manage',
                'icon' => 'ico10',
            ];
            $menu['im_seller']['submenu']['my_store'] = [
                'text' => Lang::get('my_store'),
                'url'  => 'index.php?app=my_store',
                'name' => 'my_store',
                'icon' => 'ico11',
            ];
            $menu['im_seller']['submenu']['my_theme'] = [
                'text' => Lang::get('my_theme'),
                'url'  => 'index.php?app=my_theme',
                'name' => 'my_theme',
                'icon' => 'ico12',
            ];
            $menu['im_seller']['submenu']['my_payment'] = [
                'text' => Lang::get('my_payment'),
                'url'  => 'index.php?app=my_payment',
                'name' => 'my_payment',
                'icon' => 'ico13',
            ];
            $menu['im_seller']['submenu']['my_shipping'] = [
                'text' => Lang::get('my_shipping'),
                'url'  => 'index.php?app=my_shipping',
                'name' => 'my_shipping',
                'icon' => 'ico14',
            ];
            $menu['im_seller']['submenu']['my_navigation'] = [
                'text' => Lang::get('my_navigation'),
                'url'  => 'index.php?app=my_navigation',
                'name' => 'my_navigation',
                'icon' => 'ico15',
            ];
            $menu['im_seller']['submenu']['my_partner'] = [
                'text' => Lang::get('my_partner'),
                'url'  => 'index.php?app=my_partner',
                'name' => 'my_partner',
                'icon' => 'ico16',
            ];
            $menu['im_seller']['submenu']['coupon'] = [
                'text' => Lang::get('coupon'),
                'url'  => 'index.php?app=coupon',
                'name' => 'coupon',
                'icon' => 'ico19',
            ];
        }

        return $menu;
    }
}

/**
 *    店铺管理子系统基础类
 *
 * @author    Garbin
 * @usage     none
 */
class StoreadminbaseApp extends MemberbaseApp
{
    function _run_action()
    {
        /* 只有登录的用户才可访问 */
        if ( !$this->visitor->has_login && !in_array(ACT, [ 'login', 'register', 'check_user' ]) ) {
            if ( !IS_AJAX && !IS_WECHAT ) {
                //   header('Location:index.php?app=member&act=login&ret_url=' . rawurlencode($_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']));
                //   return;
            } else {
                $this->ej_json_failed(3004);
                exit();
            }
        }

        /* 检查是否是店铺管理员 */
        if ( !$this->visitor->get('manage_store') ) {
            /* 您不是店铺管理员 */
            return $this->ej_json_failed(-1, Lang::get('not_storeadmin'));
        }

        /* 检查是否被授权 */
        $privileges = $this->_get_privileges();
        if ( !$this->visitor->i_can('do_action', $privileges) ) {
            return $this->ej_json_failed(-1, Lang::get('no_permission'));
        }

        /* 检查店铺开启状态 */
        $state = $this->visitor->get('state');
        if ( $state == 0 ) {
            return $this->ej_json_failed(-1, Lang::get('apply_not_agree'));
        } elseif ( $state == 2 ) {
            $userID = intval($this->visitor->get('user_id'));
            # Todo 考虑缓存...
            $orderStatus = get_stats($userID, 'seller_id'); // 订单状态
            if ( !( $orderStatus['order_pending'] != 0 || $orderStatus['order_accepted'] != 0 || $orderStatus['order_shipped'] != 0 ) ) {
                return $this->ej_json_failed(-1, Lang::get('store_is_closed'));
            }
        }

        /* 检查附加功能 */
        if ( !$this->_check_add_functions() ) {
            return $this->ej_json_failed(-1, Lang::get('not_support_function'));
        }

        parent::_run_action();
    }

    function _get_privileges()
    {
        $store_id = $this->visitor->get('manage_store');
        $privs = $this->visitor->get('s');

        if ( empty( $privs ) ) {
            return '';
        }

        foreach ( $privs as $key => $admin_store ) {
            if ( $admin_store['store_id'] == $store_id ) {
                return $admin_store['privs'];
            }
        }
    }

    /* 获取当前店铺所使用的主题 */
    function _get_theme()
    {
        $model_store =& m('store');
        $store_info = $model_store->get($this->visitor->get('manage_store'));
        $theme = !empty( $store_info['theme'] ) ? $store_info['theme'] : 'default|default';
        list( $curr_template_name, $curr_style_name ) = explode('|', $theme);

        return [
            'template_name' => $curr_template_name,
            'style_name'    => $curr_style_name,
        ];
    }

    function _check_add_functions()
    {
        $apps_functions = [ // app与function对应关系
            'seller_groupbuy' => 'groupbuy',
            'coupon'          => 'coupon',
        ];
        if ( isset( $apps_functions[ APP ] ) ) {
            $store_mod =& m('store');
            $settings = $store_mod->get_settings($this->_store_id);
            $add_functions = isset( $settings['functions'] ) ? $settings['functions'] : ''; // 附加功能
            if ( !in_array($apps_functions[ APP ], explode(',', $add_functions)) ) {
                return false;
            }
        }

        return true;
    }
}

/**
 *    店铺控制器基础类
 *
 * @author    Garbin
 * @usage     none
 */
class StorebaseApp extends FrontendApp
{
    var $_store_id;

    /**
     * 设置店铺id
     *
     * @param int $store_id
     */
    function set_store( $store_id )
    {
        $this->_store_id = intval($store_id);

        /* 有了store id后对视图进行二次配置 */
        $this->_init_view();
        $this->_config_view();
    }

    function _config_view()
    {
        parent::_config_view();
        $template_name = $this->_get_template_name();
        $style_name = $this->_get_style_name();

        $this->_view->template_dir = ROOT_PATH . "/themes/store/{$template_name}";
        $this->_view->compile_dir = ROOT_PATH . "/temp/compiled/store/{$template_name}";
        $this->_view->res_base = SITE_URL . "/themes/store/{$template_name}/styles/{$style_name}";
    }

    /**
     * 取得店铺信息
     */
    function get_store_data()
    {
        $cache_server =& cache_server();
        $key = 'function_get_store_data_' . $this->_store_id;
        $store = $cache_server->get($key);
        if ( $store === false ) {
            $store = $this->_get_store_info();
            /** 根据需求解除店铺限制
             * if ( empty( $store ) ) {
             * $this->show_warning('the_store_not_exist');
             * exit;
             * }
             * if ( $store['state'] == 2 ) {
             * $this->show_warning('the_store_is_closed');
             * exit;
             * }**/
            $step = intval(Conf::get('upgrade_required'));
            $step < 1 && $step = 5;
            $store_mod =& m('store');
            $store['credit_image'] = $this->_view->res_base . '/images/' . $store_mod->compute_credit($store['credit_value'], $step);

            empty( $store['store_logo'] ) && $store['store_logo'] = Conf::get('default_store_logo');
            $store['store_owner'] = $this->_get_store_owner();
            $store['store_navs'] = $this->_get_store_nav();
            $goods_mod =& m('goods');
            $store['goods_count'] = $goods_mod->get_count_of_store($this->_store_id);
            $store['store_gcates'] = $this->_get_store_gcategory();
            $store['sgrade'] = $this->_get_store_grade('grade_name');
            $functions = $this->_get_store_grade('functions');
            $store['functions'] = [];
            if ( $functions ) {
                $functions = explode(',', $functions);
                foreach ( $functions as $k => $v ) {
                    $store['functions'][ $v ] = $v;
                }
            }
            $cache_server->set($key, $store, 1800);
        }

        return $store;
    }

    /* 取得店铺信息 */
    function _get_store_info()
    {
        if ( !$this->_store_id ) {
            /* 未设置前返回空 */
            return [];
        }
        static $store_info = null;
        if ( $store_info === null ) {
            $store_mod =& m('store');
            $store_info = $store_mod->get_info($this->_store_id);
        }

        return $store_info;
    }

    /* 取得店主信息 */
    function _get_store_owner()
    {
        $user_mod =& m('member');
        $user = $user_mod->get($this->_store_id);

        return $user;
    }

    /* 取得店铺导航 */
    function _get_store_nav()
    {
        $article_mod =& m('article');

        return $article_mod->find([
            'conditions' => "store_id = '{$this->_store_id}' AND cate_id = '" . STORE_NAV . "' AND if_show = 1",
            'order'      => 'sort_order',
            'fields'     => 'title',
        ]);
    }

    /*  取的店铺等级   */

    function _get_store_grade( $field )
    {
        $store_info = $store_info = $this->_get_store_info();
        $sgrade_mod =& m('sgrade');
        $result = $sgrade_mod->get_info($store_info['sgrade']);

        return $result[ $field ];
    }

    /* 取得店铺分类 */
    function _get_store_gcategory()
    {
        $gcategory_mod =& bm('gcategory', [ '_store_id' => $this->_store_id ]);
        $gcategories = $gcategory_mod->get_list(-1, true);
        import('tree.lib');
        $tree = new Tree();
        $tree->setTree($gcategories, 'cate_id', 'parent_id', 'cate_name');

        return $tree->getArrayList(0);
    }

    /**
     *    获取当前店铺所设定的模板名称
     *
     * @author    Garbin
     * @return    string
     */
    function _get_template_name()
    {
        $store_info = $this->_get_store_info();
        $theme = !empty( $store_info['theme'] ) ? $store_info['theme'] : 'default|default';
        list( $template_name, $style_name ) = explode('|', $theme);

        return $template_name;
    }

    /**
     *    获取当前店铺所设定的风格名称
     *
     * @author    Garbin
     * @return    string
     */
    function _get_style_name()
    {
        $store_info = $this->_get_store_info();
        $theme = !empty( $store_info['theme'] ) ? $store_info['theme'] : 'default|default';
        list( $template_name, $style_name ) = explode('|', $theme);

        return $style_name;
    }
}

/* 实现消息基础类接口 */

class MessageBase extends MallbaseApp
{
}

;

/* 实现模块基础类接口 */

class BaseModule extends FrontendApp
{
}

;

/* 消息处理器 */
require( ROOT_PATH . '/eccore/controller/message.base.php' );

?>
