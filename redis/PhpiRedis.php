<?php

if(!class_exists('RedisException')){
	class RedisException extends \Exception {}
}

class PhpiRedis
{
	private $_redis = null;
	private $_isConnected = false;

	/**
	 * __construct 
	 * 
	 * @access public
	 * @return vold
	 */
	public function __construct()
	{
	}

	/**
	 * __destruct 
	 * 
	 * @access public
	 * @return vold
	 */
	//神奇的问题，待查
	public function __destruct()
	{
		//if(!IS_CLI) $this->close();
	}

	/**
	 * connect redis server
	 * 
	 * @param string $host
	 * @param int $port
	 * @param int $timeout
	 * @access public
	 * @return bool
	 */
	public function connect($host, $port, $timeout = 1, $password = '')
	{
		$this->_redis = new Redis();
		$this->_isConnected = $this->_redis->connect($host, intval($port), $timeout);
		if($this->isConnected()){
			if(!empty($password)){
				return $this->auth($password);
			}
			return true;
		}else{
			return false;
		}
	}

	/**
	 * if it has connected to the redis server
	 * 
	 * @access public
	 * @return bool
	 */
	public function isConnected()
	{
		return $this->_redis && $this->_isConnected;
	}

	public function auth($password)
	{
		return $this->_redis->auth($password);
	}

	/**
	 * close the redis connection 
	 * 
	 * @access public
	 * @return void
	 */
	public function close()
	{
		if(!$this->isConnected()) return false;
		$r = $this->_redis->close();
		$this->_isConnected = false;
		$this->_redis = null;
		return $r;
	}

	//返回删除的key的数量
	public function del($keys)
	{
		return $this->_redis->del($keys);
	}

	public function delete($keys)
	{
		return $this->_redis->del($keys);
	}

	//有一个存在即返回 true 
	public function exists($key)
	{
		return $this->_redis->exists($key);
	}

	public function expire($key, $seconds)
	{
		return $this->_redis->expire($key, $seconds);
	}
	

	public function setTimeout($key, $seconds)
	{
		return $this->_redis->expire($key, $seconds);
	}
	
	public function expireAt($key, $timestamp)
	{
		return $this->_redis->expireAt($key, $timestamp);
	}

	public function keys($pattern)
	{
		return $this->_redis->keys($pattern);
	}
	
	public function persist($key)
	{
		return $this->_redis->persist($key);
	}

	public function randomKey()
	{
		return $this->_redis->randomKey();
	}

	//return Array, boolean: This function will return an array of keys or FALSE if Redis returned zero keys
	public function scan(&$cursor, $count = 50)
	{
		return $this->_redis->scan($cursor);
	}

	public function rename($key, $newkey)
	{
		return $this->_redis->rename($key, $newkey);
	}

	public function renameNx($key, $newkey)
	{
		return $this->_redis->renameNx($key, $newkey);
	}

	/*
	 * Sort the elements in a list, set or sorted set
	 *
	 * $options:
	 *     'by' => 'some_pattern_*',
	 *     'limit' => array(0, 1),
	 *     'get' => 'some_other_pattern_*' or an array of patterns,
	 *     'sort' => 'asc' or 'desc',
	 *     'alpha' => TRUE,
	 *     'store' => 'external-key'
	 * 
	 * @param string $key 
	 * @param array $options 
	 * @access public
	 * @return array
	 */
	public function sort($key, $options)
	{
		return $this->_redis->sort($key, $options);
	}

	//If the key has no ttl, -1 will be returned, and -2 if the key doesn't exist.
	public function ttl($key)
	{
		return $this->_redis->ttl($key);
	}

	public function type($key)
	{
		return $this->_redis->type($key);
	}

	//return Size of the value after the append
	public function append($key, $value)
	{
		return $this->_redis->append($key, $value);
	}

	public function decr($key, $num = 0)
	{
		if($num == 0){
			return $this->_redis->decr($key);
		}else{
			return $this->_redis->decr($key, $num);
		}
	}

	public function decrBy($key, $num)
	{
		return $this->_redis->decrBy($key, $num);
	}

	public function get($key)
	{
		return $this->_redis->get($key);
	}

	//return the bit value (0 or 1)
	public function getBit($key, $offset)
	{
		return $this->_redis->getBit($key, $offset);
	}

	public function getRange($key, $start, $end)
	{
		return $this->_redis->getRange($key, $start, $end);
	}

	//return A string, the previous value located at this key.
	public function getSet($key, $value)
	{
		return $this->_redis->getSet($key, $value);
	}

	public function incr($key, $num = 0)
	{	
		if($num == 0){
			return $this->_redis->incr($key);
		}else{
			return $this->_redis->incr($key, $num);
		}
	}

	public function incrBy($key, $num)
	{
		return $this->_redis->incrBy($key, $num);
	}


	public function mget($keys)
	{
		return $this->_redis->mGet($keys);
	}
	
	public function getMultiple($keys = array())
	{
		return $this->_redis->mGet($keys);
	}
	
	//Bool TRUE in case of success, FALSE in case of failure.
	public function mset($key_values)
	{
		return $this->_redis->mset($key_values);  
	}

	//MSETNX only returns TRUE if all the keys were set (see SETNX).
	public function msetnx($key_values)
	{
		return $this->_redis->mSetNX($key_values);  
	}

	public function set($key, $value)
	{
		return $this->_redis->set($key, $value);  
	}

	/**
	 * 设置缓存毫秒级过期时间，附带nx特性。
	 * 成功返回OK，失败返回nil
	 */
	public function setmnx($key, $value, $million)
	{
		return $this->_redis->set($key, $value, ['nx', 'px'=>$million]);
	}

	public function pset($key, $value, $million)
	{
		return $this->_redis->set($key, $value, ['xx', 'px'=>$million]);
	}
	
	public function setBit($key, $offset, $value)
	{
		return $this->_redis->setBit($key, $offset, $value);  
	}
	
	public function setex($key, $timeout, $value)
	{
		return $this->_redis->setex($key, $timeout, $value);  
	}

	public function setnx($key, $value)
	{
		return $this->_redis->setnx($key, $value);  
	}

	public function setRange($key, $offset, $value)
	{
		return $this->_redis->setnx($key, $offset, $value);  
	}

	public function strlen($key)
	{
		return $this->_redis->strlen($key);
	}

	public function hDel($key, $field)
	{
		if(is_array($field)) {
			return $this->_redis->hDel($key, ...$field);
		}
		return $this->_redis->hDel($key, $field);
	}

	public function hExists($key, $field)
	{
		return $this->_redis->hExists($key, $field);
	}

	public function hGet($key, $field)
	{
		return $this->_redis->hGet($key, $field);
	}

	public function hGetAll($key)
	{
		return $this->_redis->hGetAll($key);
	}

	public function hIncrBy($key, $field, $num)
	{
		return $this->_redis->hIncrBy($key, $field, $num);
	}

	public function hKeys($key)
	{
		return $this->_redis->hKeys($key); 
	}

	public function hLen($key)
	{
		return $this->_redis->hLen($key);
	}

	public function hMGet($key, $fields)
	{
		if(!is_array($fields) or !$fields){
			throw new RedisException("fields of hmget should be an non-empty array");
		}
		return $this->_redis->hMGet($key, $fields);
	}

	public function hMSet($key, $field_values)
	{
		if(!is_array($field_values) || !$field_values)
		{
			throw new RedisException("fields of hmget should be an non-empty array");
		}
		return $this->_redis->hMSet($key, $field_values);
	}

	public function hSet($key, $field, $value)
	{
		return $this->_redis->hSet($key, $field, $value);
	}

	public function hSetNx($key, $field, $value)
	{
		return $this->_redis->hSetNx($key, $field, $value);
	}

	public function hVals($key)
	{
		return $this->_redis->hVals($key);
	}

	public function blPop($keys, $timeout)
	{
		if(!$keys || !is_array($keys) || count($keys)==0) throw new RedisException('key should be array and don\'t empty');
		return $this->_redis->blPop($keys, $timeout);
	}

	public function brPop($keys, $timeout)
	{
		if(!$keys || !is_array($keys) || count($keys) == 0) throw new RedisException('key should be array and don\'t empty');
		return $this->_redis->brPop($keys, $timeout);
	}

	public function brpoplpush($src, $dst, $timeout)
	{
		return $this->_redis->bRPopLPush($src, $dst, $timeout);
	}

	public function lIndex($key, $index)
	{
		return $this->_redis->lIndex($key, $index);
	}

	public function lLen($key)
	{
		return $this->_redis->lLen($key);
	}

	public function lPop($key)
	{
		return $this->_redis->lPop($key);
	}

	public function lPush($key, $val)
	{
		return $this->_redis->lPush($key);
	}

	public function lPushx($key, $value)
	{
		return $this->_redis->lPushx($key, $value);
	}

	public function lRange($key, $start, $stop)
	{
		return $this->_redis->lRange($key, $start, $stop);
	}

	public function lRem($key, $value, $count)
	{
		if(!is_int($count)) return false;
		return $this->_redis->lRem($key, $value, $count);
	}

	public function lSet($key, $index, $value)
	{
		return $this->_redis->lSet($key, $index, $value);
	}

	public function lTrim($key, $start, $stop)
	{
		return $this->_redis->lTrim($key, $start, $stop);
	}


	public function rPop($key)
	{
		return $this->_redis->rPop($key); 
	}
	
	/**
	 * Remove the last element in a list, append it to another list and return it
	 * 
	 * @param string $src 
	 * @param string $dst 
	 * @access public
	 * @return bool
	 */
	public function rpoplpush($src, $dst)
	{
		return $this->_redis->rPopLPush($src, $dst); 
	}

	/**
	 * Append one or multiple values to a list
	 * 
	 * @param string $key 
	 * @param mixed $val 
	 * @access public
	 * @return int
	 */
	public function rPush($key, $value)
	{
		return $this->_redis->rPush($key, $value); 
	}

	/**
	 * Append a value to a list, only if the list exists 
	 * 
	 * @param string $key 
	 * @param mixed $value 
	 * @access public
	 * @return int
	 */
	public function rPushx($key, $value)
	{
		return $this->_redis->rPushx($key, $value); 
	}

	/**
	 * Add one or more members to a set
	 * 
	 * @param string $key 
	 * @param mixed $value 
	 * @access public
	 * @return bool
	 */
	public function sAdd($key, $value)
	{
		if(!is_array($value)){
	   		return $this->_redis->sAdd($key, $value); 
		}else{
			return $this->_redis->sAdd($key, ...$value); 
		}
	}

	/**
	 * Get the number of members in a set 
	 * 
	 * @param string $key 
	 * @access public
	 * @return int
	 */
	public function sCard($key)
	{
		return $this->_redis->sCard($key); 
	}

	/**
	 * Get the number of members in a set 
	 * 
	 * @param string $key 
	 * @access public
	 * @return int
	 */
	public function sSize($key)
	{
		return $this->sCard($key);
	}
	
	/**
	 * Subtract multiple sets
	 * 
	 * @param array $keys 
	 * @access public
	 * @return bool
	 */
	public function sDiff($keys)
	{
		return $this->_redis->sDiff(...$keys); 
	}


	/**
	 * Intersect multiple sets
	 * 
	 * @param array $keys 
	 * @access public
	 * @return bool
	 */
	public function sInter($keys)
	{
		return $this->_redis->sInter(...$keys); 
	}

	/**
	 * Determine if a given value is a member of a set 
	 * 
	 * @param string $key 
	 * @param mixed $member 
	 * @access public
	 * @return bool
	 */
	public function sIsMember($key, $member)
	{
		return $this->_redis->sIsMember($key, $member); 
	}

	/**
	 * Get all the members in a set
	 * 
	 * @param string $key 
	 * @access public
	 * @return bool
	 */
	public function sMembers($key)
	{
		return $this->_redis->sMembers($key);
	}

	/**
	 * Move a member from one set to another 
	 * 
	 * @param string $source 
	 * @param string $dist 
	 * @param mixed $member 
	 * @access public
	 * @return bool
	 */
	public function sMove($source, $dist, $member)
	{
		return $this->_redis->sMove($source, $dist, $member);
	}

	/**
	 * Remove and return a random member from a set
	 * 
	 * @param string $key 
	 * @access public
	 * @return string
	 */
	public function sPop($key)
	{
		return $this->_redis->sPop($key);
	}

	/**
	 * Get a random member from a set 
	 * 
	 * @param string $key 
	 * @access public
	 * @return string
	 */
	public function sRandMember($key, $num = 1)
	{
		return $this->_redis->sRandMember($key, $num);
	}

	/**
	 * Remove one or more members from a set
	 * 
	 * @param string $key 
	 * @param mixed $val 
	 * @access public
	 * @return int
	 */
	public function sRem($key, $member)
	{
		if(is_array($member)){
			return $this->_redis->sRem($key, ...$member);
		}else{
			return $this->_redis->sRem($key, $member);
		}
	}

	/**
	 * Add multiple sets
	 * 
	 * @param array $keys 
	 * @access public
	 * @return array
	 */
	public function sUnion($keys)
	{
		return $this->_redis->sUnion($keys);
	}
	
	/**
	 * Add multiple sets and store the resulting set in a key
	 * 
	 * @param string $dst 
	 * @param array $keys 
	 * @access public
	 * @return int
	 */
	public function sUnionStore($dst, array $keys)
	{
		return $this->_redis->sUnionStore($dst, ...$keys);
	}

	/**
	 * Add one or more members to a sorted set, or update its score if it already exists
	 * 
	 * @param string $key 
	 * @param int $score 
	 * @param mixed $member 
	 * @access public
	 * @return bool
	 */
	public function zAdd($key, $score, $member)
	{
		return $this->_redis->zAdd($key, $score, $member);
	}

	/**
	 * Add more members to a sorted set batch, or update its score if it already exists
	 *
	 * @param string $key
	 * @param array $member [$member=>$score, $member1=>$score1, ...]
	 * @access public
	 * @return bool
	 */
	public function zMAdd($key, $members)
	{
		$num = 0;
		foreach($members as $member => $score){
			$num += $this->_redis->zAdd($key, $score, $member);
		}
		return $num;
	}

	/**
	 * Count the members in a sorted set with scores within the given values
	 * 
	 * @param string $key 
	 * @param int $min 
	 * @param int $max 
	 * @access public
	 * @return int
	 */
	public function zCount($key, $min, $max)
	{
		return $this->_redis->zCount($key, $min, $max);
	}

	/**
	 * Get the number of members in a sorted set
	 * 
	 * @param string $key 
	 * @access public
	 * @return int
	 */
	public function zCard($key)
	{
		return $this->_redis->zCard($key);
	} 

	/**
	 * Increment the score of a member in a sorted set
	 * 
	 * @param string $key 
	 * @param int $num 
	 * @param mixed $member 
	 * @access public
	 * @return int
	 */
	public function zIncrBy($key, $num, $member)
	{
		return $this->_redis->zIncrBy($key, $num, $member);
	}

	/**
	 * Intersect multiple sorted sets and store the resulting sorted set in a new key
	 * 
	 * @param string $dst 
	 * @param array $keys 
	 * @param array $weights 
	 * @param string $aggregate 
	 * @access public
	 * @return int
	 */
	public function zInterStore($dst, $keys, $weights = array(), $aggregate = '')
	{
		return $this->_redis->zInterStore($dst, $keys, $weights, $aggregate);
	}

	/**
	 * Intersect multiple sorted sets and store the resulting sorted set in a new key
	 * 
	 * @param string $dst 
	 * @param array $keys 
	 * @param array $weights 
	 * @param string $aggregate 
	 * @access public
	 * @return int
	 */
	public function zInter($dst, $keys, $weights = array(), $aggregate = '')
	{
		return $this->zInter($dst, $keys, $weights, $aggregate);
	}

	/**
	 * Return a range of members in a sorted set, by index
	 * 
	 * @param string $key 
	 * @param int $start 
	 * @param int $stop 
	 * @param bool $is_withscores 
	 * @access public
	 * @return array
	 */
	public function zRange($key, $start, $stop, $is_withscores=false)
	{
		return $this->_redis->zRange($key, $start, $stop, $is_withscores);
	}

	/**
	 * Return a range of members in a sorted set, by score
	 * 
	 * @param string $key 
	 * @param int $min 
	 * @param int $max 
	 * @param array $extra 
	 * @access public
	 * @return array
	 */
	public function zRangeByScore($key, $start, $stop, $extra = array())
	{
		return $this->_redis->zRangeByScore($key, $start, $stop, $extra);
	}

	/**
	 * Determine the index of a member in a sorted set
	 * 
	 * @param string $key 
	 * @param mixed $member 
	 * @access public
	 * @return int
	 */
	public function zRank($key, $member)
	{
		return $this->_redis->zRank($key, $member);
	}

	/**
	 * Remove one or more members from a sorted set
	 * 
	 * @param string $key 
	 * @param mixed $members 
	 * @access public
	 * @return bool
	 */
	public function zRem($key, $members)
	{
		if(is_array($members)){
			foreach($members as $member){
				$this->_redis->zRem($key, $member);
			}
			return true;
		}else{
			return $this->_redis->zRem($key, $members);
		}
	}

	/**
	 * Remove all members in a sorted set within the given indexes 
	 * 
	 * @param string $key 
	 * @param int $start 
	 * @param int $stop 
	 * @access public
	 * @return array
	 */
	public function zRemRangeByRank($key, $start, $stop)
	{
		return $this->_redis->zRemRangeByRank($key, $start, $stop);
	}

	public function zRemRangeByScore($key, $start, $stop)
	{
		return $this->_redis->zRemRangeByScore($key, $start, $stop);
	}

	/**
	 * Return a range of members in a sorted set, by index, with scores ordered from high to low
	 * 
	 * @param string $key 
	 * @param int $start 
	 * @param int $end 
	 * @param bool $is_withscores 
	 * @access public
	 * @return bool
	 */
	public function zRevRange($key, $start, $end, $is_withscores=false)
	{
		return $this->_redis->zRevRange($key, $start, $end, $is_withscores);
	}

	/**
	 * Return a range of members in a sorted set, by score, with scores ordered from high to low
	 * 
	 * @param string $key 
	 * @param int $max 
	 * @param int $min 
	 * @param array $extra 
	 * @access public
	 * @return array
	 */
	public function zRevRangeByScore($key, $max, $min, $extra = array()) 
	{
		return $this->_redis->zRevRangeByScore($key, $max, $min, $extra);
	}

	public function zRevRank($key, $member)
	{
		return $this->_redis->zRevRank($key, $member);
	}

	public function zScore($key, $member)
	{
		return $this->_redis->zScore($key, $member);
	}

	public function zUnionStore($dst, $keys, $weights = array(), $aggregate='')
	{
		if(!$keys || !is_array($keys) || count($keys) == 0) throw new RedisException('the second parameter need a not null array');
		return $this->_redis->zUnionStore($dst, $keys, $weights, $aggregate);
	}

	public function discard()
	{
		throw new \Exception("called exec with wrong way");
	}

	public function exec()
	{
		throw new \Exception("called exec with wrong way");
	}

	public function multi()
	{
		return $this->_redis->multi();
	}

	public function unwatch()
	{
		return $this->_redis->unwatch();
	}

	public function watch($keys)
	{
		return $this->_redis->watch($keys);
	}

	public function ping()
	{
		return $this->_redis->ping();
	}

	public function info()
	{
		return $this->_redis->info();
	}

	public function flushDB()
	{
		return $this->_redis->flushDB();
	}

	public function dbSize()
	{
		return $this->_redis->dbSize();
	}

    public function eval($lua, $data, $num)
	{
		return $this->_redis->eval($lua, $data, $num);
	}
}