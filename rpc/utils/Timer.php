<?php

namespace Imee\Comp\Common\Rpc\Utils;

class Timer
{
    private static $now = [];

    public static function start($name)
    {
        if (!isset(self::$now[$name])) {
            self::$now[$name] = microtime(true);
        }
    }

    public static function pause($name)
    {
        return round((microtime(true) - self::$now[$name]) * 1000, 2);
    }

    public static function stop($name)
    {
        $t = round((microtime(true) - self::$now[$name]) * 1000, 2);
        unset(self::$now[$name]);
        return $t;
    }
}