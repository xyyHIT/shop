<?php

/**
 *    支付方式管理控制器
 *
 *    @author    Garbin
 *    @usage    none
 */
class PaymentApp extends BackendApp
{
    function index()
    {
        /* 读取已安装的支付方式 */
        $model_payment =& m('payment');
        $payments      = $model_payment->get_builtin();
        $white_list    = $model_payment->get_white_list();
        foreach ($payments as $key => $value)
        {
            $payments[$key]['system_enabled'] = in_array($key, $white_list);
        }
        $this->assign('payments', $payments);
        $this->display('payment.index.html');
    }

    /**
     *    启用
     *
     *    @author    Garbin
     *    @return    void
     */
    function enable()
    {
        $code = isset($_GET['code'])    ? trim($_GET['code']) : 0;
        if (!$code)
        {
            $this->show_warning('no_such_payment');

            return;
        }
		$model_payment =& m('payment');
		//检验是否启用，禁止恶意访问！
		$ejpaymentsql = "select payment_id from ".DB_PREFIX."ejpayment where payment_code='".$code."'";
		if($model_payment->db->getOne($ejpaymentsql)){
			$this->show_warning($model_payment->get_error());
            return;	
		}
        if (!$model_payment->enable_builtin($code))
        {
            $this->show_warning($model_payment->get_error());

            return;
        }
		//更改支付方式存储方式   由于ej商城需要存储平台  进而沿用支付方式表方式
        /* 取得列表数据 获取白名单*/
        $white_list    = $model_payment->get_white_list();
        /* 获取白名单过滤后的内置支付方式列表 */
        $payments      = $model_payment->get_builtin($white_list);
		$sqlfields = 'ejpayment(payment_code,payment_name,payment_desc,enabled)';
		$model_payment->db->query('INSERT INTO '.DB_PREFIX.$sqlfields." VALUES('".$code."','".$payments[$code]['name']."','".$payments[$code]['desc']."','1')");
        $this->show_message('enable_payment_successed');

    }

    /**
     *    禁用
     *
     *    @author    Garbin
     *    @return    void
     */
    function disable()
    {
        $code = isset($_GET['code'])    ? trim($_GET['code']) : 0;
        if (!$code)
        {
            $this->show_warning('no_such_payment');

            return;
        }
        $model_payment =& m('payment');
		//检验是否启用，禁止恶意访问！
		$ejpaymentsql = "select payment_id from ".DB_PREFIX."ejpayment where payment_code='".$code."'";
		$paymentid = $model_payment->db->getOne($ejpaymentsql);
		if(!$paymentid){
			$this->show_warning($model_payment->get_error());
            return;	
		}
        if (!$model_payment->disable_builtin($code))
        {
            $this->show_warning($model_payment->get_error());

            return;
        }
		$model_payment->db->query("DELETE FROM ".DB_PREFIX."ejpayment where payment_id=".$paymentid);
        $this->show_message('disable_payment_successed');
    }
}

?>