<?php
/*******************************************************************************
 * 艺加商城
 *
 * (c)  2016  Gavin(田宇)  <tianyu_0723@hotmail.com>
 *
 ******************************************************************************/

/**
 * Created by PhpStorm.
 * User: Gavin
 * Date: 2016/12/9
 * Time: 15:28
 */

use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

class Log
{
    /**
     * logger实例
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected static $logger;

    /**
     * 返回logger实例
     *
     * @return \Psr\Log\LoggerInterface
     */
    public static function getLogger()
    {
        return self::$logger ?: self::$logger = self::createDefaultLogger();
    }

    /**
     * 设置logger
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public static function setLogger(LoggerInterface $logger)
    {
        self::$logger = $logger;
    }

    /**
     * logger是否存在
     *
     * @return bool
     */
    public static function hasLogger()
    {
        return self::$logger ? true : false;
    }

    /**
     * 静态魔术方法调用
     *
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return forward_static_call_array([self::getLogger(), $method], $args);
    }

    /**
     * 魔术方法调用
     *
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([self::getLogger(), $method], $args);
    }

    /**
     * 设置默认logger
     *
     * @return \Monolog\Logger
     */
    private static function createDefaultLogger()
    {
//        $log = new Logger('EJMall');
//        $log->pushHandler(new ErrorLogHandler());
        $logger = new Logger('EJMall');

        $filePath = ROOT_PATH.'/temp/mall/';
        $fileName = date('Y-m-d').'.log';

        $logger->pushHandler(new StreamHandler($filePath.$fileName, Logger::WARNING));

        return $logger;
    }

    public static function initializeLogger()
    {
        if (Log::hasLogger()) {
            return;
        }

        $logger = new Logger('EJMall');

        $logger->pushHandler(new StreamHandler(ROOT_PATH . '/temp/wechat.log', Logger::DEBUG));

//        if (!$this['config']['debug'] || defined('PHPUNIT_RUNNING')) {
//            $logger->pushHandler(new NullHandler());
//        } elseif ($this['config']['log.handler'] instanceof HandlerInterface) {
//            $logger->pushHandler($this['config']['log.handler']);
//        } elseif ($logFile = $this['config']['log.file']) {
//            $logger->pushHandler(new StreamHandler($logFile, $this['config']->get('log.level', Logger::WARNING)));
//        }

        Log::setLogger($logger);
    }
}