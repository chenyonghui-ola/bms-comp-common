<?php

namespace Imee\Comp\Common\Redis;

//最近的数据记录，拨入某一类事件的最近操作
class RedisLastLog extends RedisBase
{
    private $_type;
    private $_num;

    public function __construct($type, $num = 100, $key = self::REDIS_CACHE)
    {
        $this->_type = $type;
        $this->_num = $num;
        parent::__construct($key);
    }

    public function get()
    {
        if (!$this->connect()) return false;
        $res = $this->_redis->lrange($this->_type, 0, -1);
        if ($res === false) return array();
        $data = array();
        foreach ($res as $val) {
            $data[] = $this->unserialize($val);
        }
        return $data;
    }

    public function set($val)
    {
        if (!$this->connect()) return false;
        $this->_redis->lpush($this->_type, $this->serialize($val));
        $this->_redis->ltrim($this->_type, 0, $this->_num - 1);
        return true;
    }
}