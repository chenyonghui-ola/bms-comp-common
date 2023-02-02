<?php

namespace Imee\Comp\Common\Redis;

class RedisQueue extends RedisBase
{
    protected $_name;

    public function __construct($name, $key = self::REDIS_LOG)
    {
        parent::__construct($key);
        $this->_name = $name;
    }

    //获取一个元素， first
    public function get()
    {
        $this->checkConnect();
        $res = $this->_redis->lPop($this->_name);
        if ($res === false) return false;
        return $this->unserialize($res);
    }

    //随机获取一个元素
    public function rand()
    {
        $this->checkConnect();
        $len = $this->_redis->lLen($this->_name);
        $index = rand(0, $len);
        $res = $this->_redis->lIndex($this->_name, $index);
        if ($res === false) return false;
        //删除
        $this->_redis->lRem($this->_name, 1, $res);
        return $this->unserialize($res);
    }

    public function set($val)
    {
        $this->checkConnect();
        return $this->_redis->rPush($this->_name, $this->serialize($val));
    }

    //清空这个list
    public function clear()
    {
        $this->checkConnect();
        return $this->_redis->lTrim($this->_name, -1, -1);
    }

    //获取所有成员数量
    public function length()
    {
        $this->checkConnect();
        return $this->_redis->lLen($this->_name);
    }

    //删除指定的成员
    public function delete($member)
    {
        $this->checkConnect();
        $this->_redis->lRem($this->_name, 1, $member);
    }

    protected function checkConnect()
    {
        if (!$this->connect() && $this->_exception) {
            throw new \Exception("redis connect error, key = {$this->_connKey}");
        }
        return true;
    }
}