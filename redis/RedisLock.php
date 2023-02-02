<?php

namespace Imee\Comp\Common\Redis;

class RedisLock extends RedisBase
{
    private $_retryDelay;
    private $_retryCount;

    public function __construct($key = self::REDIS_CACHE, $exception = true, $retryDelay = 200, $retryCount = 3)
    {
        parent::__construct($key);
        $this->setConnectError($exception);
        $this->_retryCount = $retryCount;
        $this->_retryDelay = $retryDelay;
    }

    public function lock($resource, $ttl = 15)
    {
        if (!$this->connect()) return false;
        $token = uniqid();
        $retry = $this->_retryCount;
        do {
            $isOK = $this->lockInstance($resource, $token, $ttl);
            if ($isOK) {
                return true;
            }
            $delay = mt_rand(floor($this->_retryDelay / 2), $this->_retryDelay);
            usleep($delay * 1000);
            $retry--;
        } while ($retry > 0);
        return false;
    }

    public function unlock($resource)
    {
        if (!$this->connect()) return false;
        return $this->unlockInstance($resource);
    }

    private function lockInstance($resource, $token, $ttl)
    {
        if ($this->_redis->setnx($resource, $token)) {
            return $this->_redis->setex($resource, $ttl, $token);
        }
        return false;
    }

    private function unlockInstance($resource)
    {
        return $this->_redis->del($resource);
    }
}
