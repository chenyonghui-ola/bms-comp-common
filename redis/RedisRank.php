<?php

namespace Imee\Comp\Common\Redis;

//需要考虑过期时间
class RedisRank extends RedisBase
{
    protected $_name;

    const DESC = 0;
    const ASC = 1;

    public function __construct($name, $key = self::REDIS_CACHE, $exception = true)
    {
        parent::__construct($key);
        $this->_name = $name;
        $this->setConnectError($exception);
    }

    //返回key对应的排序，自高到低，从0开始
    public function get($member, $rank = self::DESC)
    {
        $this->checkConnect();
        if ($rank == self::DESC) {
            return $this->_redis->zRevRank($this->_name, $member);
        } else {
            return $this->_redis->zRank($this->_name, $member);
        }
    }

    public function isMember($member)
    {
        $this->checkConnect();
        $score = $this->_redis->zScore($this->_name, $member);
        if (is_numeric($score)) return true;
        return false;
    }

    public function getScore($member)
    {
        $this->checkConnect();
        $score = $this->_redis->zScore($this->_name, $member);
        if (is_numeric($score)) return intval($score);
        return 0;
    }

    public function sMembers()
    {
        return $this->getByIndex(0, -1);
    }

    public function zRevRank($member)
    {
        $this->checkConnect();
        return $this->_redis->zRevRank($this->_name, $member);
    }

    /*
        按照排序返回成员
        返回顺序在参数start和stop指定范围内的成员，这里start和stop参数都是0-based，即0表示第一个成员，-1表示最后一个成员。
        如果start大于该Sorted-Set中的最大索引值，或start > stop，此时一个空集合将被返回。
        如果stop大于最大索引值，该命令将返回从start到集合的最后一个成员
        $withscore=false 返回 array, 成员的列表
        $withscore=true 返回 hash 成员 => 分数
    */
    public function getByIndex($start, $stop, $withscore = false, $rank = self::ASC)
    {
        $this->checkConnect();
        if ($rank == self::DESC) {
            return $this->_redis->zRevRange($this->_name, $start, $stop, $withscore);
        } else {
            return $this->_redis->zRange($this->_name, $start, $stop, $withscore);
        }
    }


    public function getByRangeScore($start, $stop, $withscore = true)
    {
        $this->checkConnect();
        return $this->_redis->zRangeByScore($this->_name, $start, $stop, array('withscores' => $withscore));
    }


    //设置key对应的分数，返回插入的成员数量
    public function set($member, $score)
    {
        $this->checkConnect();
        return $this->_redis->zAdd($this->_name, $score, $member);
    }

    //批量设置集合的成员及其对应的分数，返回插入的成员数量
    public function zmset($members)
    {
        $this->checkConnect();
        return $this->_redis->zMAdd($this->_name, $members);
    }

    //获取所有成员数量
    public function length()
    {
        $this->checkConnect();
        return $this->_redis->zCard($this->_name);
    }

    //获取分数在此范围内的成员数
    public function count($min, $max)
    {
        $this->checkConnect();
        return $this->_redis->zCount($this->_name, $min, $max);
    }

    //对成员加分或者减少，如果成员不存在，默认为0，返回字符形式的分数
    public function incr($member, $score)
    {
        $this->checkConnect();
        return $this->_redis->zIncrBy($this->_name, $score, $member);
    }

    //删除一个或者多个成员
    public function remove($members)
    {
        if (!is_null($members)) {
            $this->checkConnect();
            return $this->_redis->zRem($this->_name, $members);
        }
        return 0;
    }

    public function clear()
    {
        $this->checkConnect();
        $this->_redis->del($this->_name);
    }

    //删除索引位置位于start和stop之间的成员，start和stop都是0-based，即0表示分数最低的成员，-1表示最后一个成员，即分数最高的成员。
    public function removeByRange($start, $stop)
    {
        $this->checkConnect();
        return $this->_redis->zRemRangeByRank($this->_name, $start, $stop);
    }

    //按照分数删除，返回删除成员数量
    public function removeByScore($min, $max)
    {
        $this->checkConnect();
        return $this->_redis->zRemRangeByScore($this->_name, $min, $max);
    }

    protected function checkConnect()
    {
        if (!$this->connect() && $this->_exception) {
            throw new \Exception("redis connect error, key = {$this->_connKey}");
        }
        return true;
    }
}