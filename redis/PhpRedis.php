<?php

namespace Imee\Comp\Common\Redis;

use Phalcon\Di;

require_once(dirname(__FILE__) . DS . 'PhpiRedis.php');

class PhpRedis
{
    private static $_used = array();

    public static function used()
    {
        return self::$_used;
    }

    public static function getInstance($key = 'redis', $exception = false)
    {
        if (isset(self::$_used[$key]) && self::$_used[$key]->isConnected()) return self::$_used[$key];
        $redis = new \PhpiRedis();
        $config = Di::getDefault()->getShared('config')->{$key};
        if ($config == null) throw new \Exception("Key {$key} not in config");
        if ($redis->connect($config['host'], $config['port'], 1, isset($config['password']) ? $config['password'] : '')) {
            self::$_used[$key] = $redis;
            return self::$_used[$key];
        } else {
            throw new \Exception("Redis connect {$key} error");
        }
    }
}
