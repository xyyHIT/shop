<?php
/*******************************************************************************
 * 艺加商城
 *
 * (c)  2016  Gavin(田宇)  <tianyu_0723@hotmail.com>
 *
 ******************************************************************************/

/**
 * Class Cache
 */
class Cache
{
    protected static $instance = [];
    public static $readTimes = 0;
    public static $writeTimes = 0;

    private static $options = [];
    /**
     * 操作句柄
     *
     * @var EJRedis
     * @access protected
     */
    protected static $handler;

    /**
     * 连接缓存
     *
     * @access public
     *
     * @param array       $options 配置数组
     * @param bool|string $name    缓存连接标识 true 强制重新连接
     *
     * @return EJRedis
     */
    public static function connect( array $options = [], $name = false )
    {
        $type = !empty( $options['type'] ) ? $options['type'] : 'File';
        if ( false === $name ) {
            $name = md5(serialize($options));
        }

        if ( true === $name || !isset( self::$instance[ $name ] ) ) {
            include_once ROOT_PATH . '/includes/cache/driver/EJ' . ucwords($type) . '.php';
            $class = 'EJ' . ucwords($type);
            // 记录初始化信息
            if ( true === $name ) {
                return new $class($options);
            } else {
                self::$instance[ $name ] = new $class($options);
            }
        }
        self::$handler = self::$instance[ $name ];

        return self::$handler;
    }

    /**
     * 自动初始化缓存
     *
     * @access public
     *
     * @param array $options 配置数组
     *
     * @return void
     */
    public static function init( array $options = [] )
    {
        if ( is_null(self::$handler) ) {
            // 自动初始化缓存
            if ( !empty( $options ) ) {
                self::$options = $options;
                self::connect(self::$options);
            } else {
                // 导入缓存设置文件
                self::$options = include_once ROOT_PATH . '/data/cache.cfg.php';
                if ( 'complex' == self::$options['type'] ) {
                    self::connect(self::$options['default']);
                } else {
                    self::connect(self::$options);
                }
            }
        }
    }

    /**
     * 切换缓存类型 需要配置 cache.type 为 complex
     *
     * @access public
     *
     * @param string $name 缓存标识
     *
     * @return Driver
     */
    public static function store( $name )
    {
        self::init();
        if ( 'complex' == self::$options['type'] ) {
            self::connect(self::$options[ $name ], strtolower($name));
        }

        return self::$handler;
    }

    /**
     * 判断缓存是否存在
     *
     * @access public
     *
     * @param string $name 缓存变量名
     *
     * @return bool
     */
    public static function has( $name )
    {
        self::init();
        self::$readTimes++;

        return self::$handler->has($name);
    }

    /**
     * 读取缓存
     *
     * @access public
     *
     * @param string $name    缓存标识
     * @param mixed  $default 默认值
     *
     * @return mixed
     */
    public static function get( $name, $default = false )
    {
        self::init();
        self::$readTimes++;

        return self::$handler->get($name, $default);
    }

    /**
     * 写入缓存
     *
     * @access public
     *
     * @param string   $name   缓存标识
     * @param mixed    $value  存储数据
     * @param int|null $expire 有效时间 0为永久
     *
     * @return boolean
     */
    public static function set( $name, $value, $expire = null )
    {
        self::init();
        self::$writeTimes++;

        return self::$handler->set($name, $value, $expire);
    }

    /**
     * 自增缓存（针对数值缓存）
     *
     * @access public
     *
     * @param string $name 缓存变量名
     * @param int    $step 步长
     *
     * @return false|int
     */
    public static function inc( $name, $step = 1 )
    {
        self::init();
        self::$writeTimes++;

        return self::$handler->inc($name, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     *
     * @access public
     *
     * @param string $name 缓存变量名
     * @param int    $step 步长
     *
     * @return false|int
     */
    public static function dec( $name, $step = 1 )
    {
        self::init();
        self::$writeTimes++;

        return self::$handler->dec($name, $step);
    }

    /**
     * 删除缓存
     *
     * @access public
     *
     * @param string $name 缓存标识
     *
     * @return boolean
     */
    public static function rm( $name )
    {
        self::init();
        self::$writeTimes++;

        return self::$handler->rm($name);
    }

    /**
     * 清除缓存
     *
     * @access public
     *
     * @param string $tag 标签名
     *
     * @return boolean
     */
    public static function clear( $tag = null )
    {
        self::init();
        self::$writeTimes++;

        return self::$handler->clear($tag);
    }

    /**
     * @return EJRedis
     */
    public static function handler()
    {
        self::init();

        return self::$handler;
    }

    /**
     * @param $key
     * @param $hashKey
     *
     * @return bool
     */
    public static function hHas($key,$hashKey){
        self::init();
        self::$readTimes++;

        return self::$handler->hHas($key,$hashKey);
    }


    /**
     * @param      $key
     * @param      $hashKey
     * @param bool $default
     *
     * @return bool|mixed|string
     */
    public static function hGet($key,$hashKey,$default = false){
        self::init();

        self::$readTimes++;

        return self::$handler->hGet($key,$hashKey,$default);
    }

    /**
     * @param $key
     * @param $hashKey
     * @param $value
     *
     * @return int
     */
    public static function hSet($key,$hashKey,$value){
        self::init();

        self::$writeTimes++;

        return self::$handler->hSet($key,$hashKey,$value);
    }

    /**
     * @param $key
     * @param $hashKey
     *
     * @return int
     */
    public static function hDel($key,$hashKey){
        self::init();
        self::$writeTimes++;

        return self::$handler->hDel($key,$hashKey);
    }

    /**
     * 缓存标签
     *
     * @access public
     *
     * @param string       $name    标签名
     * @param string|array $keys    缓存标识
     * @param bool         $overlay 是否覆盖
     *
     * @return Driver
     */
    public static function tag( $name, $keys = null, $overlay = false )
    {
        self::init();

        return self::$handler->tag($name, $keys, $overlay);
    }

}
