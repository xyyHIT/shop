<?php

include ROOT_PATH . '/includes/cache/Driver.php';

/**
 * PHP缓存驱动
 *
 * Class Redis
 */
class EJRedis extends Driver
{
    protected $options = [
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'select'     => 0,
        'timeout'    => 0,
        'expire'     => 0,
        'persistent' => false,
        'prefix'     => '',
    ];

    /**
     * 架构函数
     *
     * @param array $options 缓存参数
     *
     * @access public
     */
    public function __construct( $options = [] )
    {
        if ( !extension_loaded('redis') ) {
            throw new \BadFunctionCallException('not support: redis');
        }
        if ( !empty( $options ) ) {
            $this->options = array_merge($this->options, $options);
        }
        $func = $this->options['persistent'] ? 'pconnect' : 'connect';
        $this->handler = new Redis();
        $this->handler->$func($this->options['host'], $this->options['port'], $this->options['timeout']);

        if ( '' != $this->options['password'] ) {
            $this->handler->auth($this->options['password']);
        }

        if ( 0 != $this->options['select'] ) {
            $this->handler->select($this->options['select']);
        }
    }

    /**
     * 判断缓存
     *
     * @access public
     *
     * @param string $name 缓存变量名
     *
     * @return bool
     */
    public function has( $name )
    {
        return $this->handler->get($this->getCacheKey($name)) ? true : false;
    }

    /**
     * 读取缓存
     *
     * @access public
     *
     * @param string $name    缓存变量名
     * @param mixed  $default 默认值
     *
     * @return mixed
     */
    public function get( $name, $default = false )
    {
        $value = $this->handler->get($this->getCacheKey($name));
        if ( is_null($value) ) {
            return $default;
        }
        $jsonData = json_decode($value, true);

        // 检测是否为JSON数据 true 返回JSON解析数组, false返回源数据 byron sampson<xiaobo.sun@qq.com>
        return ( null === $jsonData ) ? $value : $jsonData;
    }

    /**
     * 写入缓存
     *
     * @access public
     *
     * @param string  $name   缓存变量名
     * @param mixed   $value  存储数据
     * @param integer $expire 有效时间（秒）
     *
     * @return boolean
     */
    public function set( $name, $value, $expire = null )
    {
        if ( is_null($expire) ) {
            $expire = $this->options['expire'];
        }
        if ( $this->tag && !$this->has($name) ) {
            $first = true;
        }
        $key = $this->getCacheKey($name);
        //对数组/对象数据进行缓存处理，保证数据完整性  byron sampson<xiaobo.sun@qq.com>
        $value = ( is_object($value) || is_array($value) ) ? json_encode($value) : $value;
        if ( is_int($expire) && $expire ) {
            $result = $this->handler->setex($key, $expire, $value);
        } else {
            $result = $this->handler->set($key, $value);
        }
        isset( $first ) && $this->setTagItem($key);

        return $result;
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
    public function inc( $name, $step = 1 )
    {
        $key = $this->getCacheKey($name);

        return $this->handler->incrby($key, $step);
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
    public function dec( $name, $step = 1 )
    {
        $key = $this->getCacheKey($name);

        return $this->handler->decrby($key, $step);
    }

    /**
     * 存入set值
     *
     * @param              $name
     * @param array|string $value
     *
     * @return bool|int
     */
    public function sAdd( $name, $value )
    {
        $key = $this->getCacheKey($name);

        if ( is_array($value) ) {
            return $this->handler->sAddArray($key, $value);
        } else {
            return $this->handler->sAdd($key, $value);
        }
    }

    /**
     * 获取set数量
     *
     * @param $name
     *
     * @return int
     */
    public function sCard( $name )
    {
        $key = $this->getCacheKey($name);

        return $this->handler->sCard($key);
    }

    /**
     * 删除缓存
     *
     * @access public
     *
     * @param string $name 缓存变量名
     *
     * @return boolean
     */
    public function rm( $name )
    {
        $this->handler->delete($this->getCacheKey($name));
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
    public function clear( $tag = null )
    {
        if ( $tag ) {
            // 指定标签清除
            $keys = $this->getTagItem($tag);
            foreach ( $keys as $key ) {
                $this->handler->delete($key);
            }
            $this->rm('tag_' . md5($tag));

            return true;
        }

        return $this->handler->flushDB();
    }

    /**
     * @param $key
     * @param $hashKey
     *
     * @return bool
     */
    public function hHas( $key, $hashKey )
    {
        return $this->handler->hGet($this - $this->getCacheKey($key), $hashKey) ? true : false;
    }

    /**
     * @param      $key
     * @param      $hashKey
     * @param bool $default
     *
     * @return bool|mixed|string
     */
    public function hGet( $key, $hashKey, $default = false )
    {
        $value = $this->handler->hGet($this - $this->getCacheKey($key), $hashKey);

        if ( is_null($value) ) {
            return $default;
        }

        $jsonData = json_decode($value, true);

        return ( null === $jsonData ) ? $value : $jsonData;
    }

    /**
     * @param $key
     * @param $hashKey
     * @param $value
     *
     * @return int
     */
    public function hSet( $key, $hashKey, $value )
    {
        // key前缀
        $key = $this->getCacheKey($key);

        // 保持数据完整性
        $value = ( is_object($value) || is_array($value) ) ? json_encode($value) : $value;

        return $this->handler->hSet($key, $hashKey, $value);
    }

    /**
     * @param $key
     * @param $hashKey
     *
     * @return int
     */
    public function hDel( $key, $hashKey )
    {
        return $this->handler->hDel($this->getCacheKey($key), $hashKey);
    }

}
