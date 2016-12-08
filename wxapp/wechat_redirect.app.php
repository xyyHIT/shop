<?php
/*******************************************************************************
 * 艺加商城
 *
 * (c)  2016  Gavin(田宇)  <tianyu_0723@hotmail.com>
 *
 ******************************************************************************/

class Wechat_redirectApp extends ECBaseApp
{
    // 商城首页
    public function index(){
        $this->redirect('/index/index.html');
    }



}