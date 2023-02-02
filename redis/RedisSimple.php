<?php

namespace Imee\Comp\Common\Redis;

/**
 * 通用的cache，会序列化数据
 */
class RedisSimple extends RedisBase
{
    public function __construct($key = self::REDIS_CACHE)
    {
        parent::__construct($key);
    }

    public function get($key)
    {
        if (!$this->connect()) return false;
        $val = $this->_redis->get($key);
        if ($val === false) return $val;
        return $this->unserialize($val);
    }

    public function set($key, $val, $second = 86400)
    {
        if (!$this->connect()) return false;
        $val = $this->serialize($val);
        $res = $this->_redis->set($key, $val);
        if ($second !== null) $this->expire($key, $second);
        return $res;
    }

    /**
     * redis 2 与 3返回的数据有差异？
     * @param $keys
     * @return array|false
     */
    public function mget($keys)
    {
        if (!$this->connect()) return false;
        $origin = $this->_redis->mget($keys);
        $data = array();
        foreach ($origin as $key => $val) {
            if (is_null($val)) $data[$key] = false;
            else $data[$key] = $this->unserialize($val);
        }
        return $data;
    }

    public function keys($pattern)
    {
        if (!$this->connect()) return false;
        return $this->_redis->keys($pattern);
    }

    public function begin()
    {
        if (!$this->connect()) return false;
        $this->_redis->multi();
    }

    public function commit()
    {
        if (!$this->connect()) return false;
        $this->_redis->exec();
    }
}