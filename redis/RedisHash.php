<?php

namespace Imee\Comp\Common\Redis;

class RedisHash extends RedisBase
{

    public function __construct($key = RedisBase::REDIS_CACHE, $exception = false)
    {
        parent::__construct($key);
        $this->setConnectError($exception);
    }

    public function get($key, $field = null)
    {
        if (!$this->checkConnect()) return false;
        if (is_null($field)) {
            return $this->_redis->hGetAll($key);
        } else if (is_array($field)) {
            return $this->_redis->hMGet($key, $field);
        } else {
            return $this->_redis->hGet($key, $field);
        }
    }

    public function set($key, $field, $val = null)
    {
        if (!$this->checkConnect()) return false;
        if ($val === null) {
            if (is_array($field)) return $this->_redis->hMSet($key, $field);
        } else {
            return $this->_redis->hSet($key, $field, $val);
        }
        throw new \Exception("arguments error");
    }

    public function has($key, $field = null)
    {
        if ($field === null) {
            return parent::has($key);
        } else {
            if (!$this->checkConnect()) return false;
            return $this->_redis->hExists($key, $field);
        }
    }

    public function hIncrBy($key, $field, $num)
    {
        if (!$this->checkConnect()) return false;
        return $this->_redis->hIncrBy($key, $field, $num);
    }

    public function delete($key, $field = null)
    {
        if ($field === null) {
            return parent::delete($key);
        } else {
            if (!$this->checkConnect()) return false;
            return $this->_redis->hDel($key, $field);
        }
    }

    protected function checkConnect()
    {
        if (!$this->connect() && $this->_exception) {
            throw new \Exception("redis connect error, key = {$this->_connKey}");
        }
        return true;
    }

    public function hLen($key)
    {
        if (!$this->checkConnect()) return false;
        return $this->_redis->hLen($key);
    }
}