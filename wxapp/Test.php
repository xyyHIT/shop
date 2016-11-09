<?php

use \dubbo\dubboClient;

class Test extends MallbaseApp {

    function dubboCli( ) {
        $options= ["registry_address" => "127.0.0.1:2181"];
        $dubboCli = new dubboClient($options);
        $HelloService = $dubboCli->getService("com.dubbo.demo.HelloService","1.0.0",null);
        $ret = $HelloService->hello("dubbo php client");

        echo $ret;
    }

}