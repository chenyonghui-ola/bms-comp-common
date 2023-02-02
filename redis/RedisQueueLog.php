<?php

namespace Imee\Comp\Common\Redis;

class RedisQueueLog extends RedisQueue
{
    const VERSION = 1;

    public function __construct($name, $key = self::REDIS_LOG)
    {
        parent::__construct($name, $key);
    }

    //获取一个元素， first
    public function get()
    {
        $this->checkConnect();
        $res = $this->_redis->lPop($this->_name);
        if ($res === false) return false;
        return $this->unserialize($res);
    }

    public function set($type, $message)
    {
        $this->checkConnect();
        return $this->_redis->rPush($this->_name, $this->serializeLog($type, $message));
    }

    protected function serializeLog($type, $message)
    {
        return pack('nA10NA*', 1, $type, time(), $this->serialize($message));
    }

    protected function unserialize($val)
    {
        $res = @unpack('nversion/A10type/Ntime/A*value', $val);
        if (isset($res['version']) && isset($res['type']) && isset($res['time']) && preg_match("/^[\d]+$/", $res['time'])) {
            $res['data'] = parent::unserialize($res['value']);
            unset($res['value']);
            return $res;
        }
        return false;
    }
}