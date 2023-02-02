<?php

namespace Imee\Comp\Common\Redis;

//集合的操作
class RedisSet extends RedisBase
{
    protected $_name;

    public function __construct($name, $key = self::REDIS_CACHE, $exception = true)
    {
        parent::__construct($key);
        $this->_name = $name;
        $this->setConnectError($exception);
    }

    public function set($member)
    {
        $this->checkConnect();
        return $this->_redis->sAdd($this->_name, $member);
    }

    public function isMember($member)
    {
        $this->checkConnect();
        return $this->_redis->sIsMember($this->_name, $member);
    }

    public function sMembers()
    {
        $this->checkConnect();
        return $this->_redis->sMembers($this->_name);
    }

    //并集
    public function sUnion($keys)
    {
        $this->checkConnect();
        $array = is_array($keys) ? $keys : array($keys);
        $array[] = $this->_name;
        return $this->_redis->sUnion($array);
    }

    //交集
    public function sInter($keys)
    {
        $this->checkConnect();
        $array = is_array($keys) ? $keys : array($keys);
        $array[] = $this->_name;
        return $this->_redis->sInter($array);
    }

    //差集
    public function sDiff($keys)
    {
        $this->checkConnect();
        $array = is_array($keys) ? $keys : array($keys);
        array_unshift($this->_name);
        return $this->_redis->sDiff($array);
    }

    public function move($member, $dest)
    {
        $this->checkConnect();
        return $this->_redis->sMove($this->_name, $dest, $member);
    }

    //获取所有成员数量
    public function length()
    {
        $this->checkConnect();
        return $this->_redis->sCard($this->_name);
    }

    //删除一个或者多个成员
    public function remove($members)
    {
        $this->checkConnect();
        $this->_redis->sRem($this->_name, $members);
    }

    public function clear()
    {
        $this->checkConnect();
        $this->delete($this->_name);
    }

    protected function checkConnect()
    {
        if (!$this->connect() && $this->_exception) {
            throw new \Exception("redis connect error, key = {$this->_connKey}");
        }
        return true;
    }
}