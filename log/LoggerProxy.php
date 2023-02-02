<?php

namespace Imee\Comp\Common\Log;

use Phalcon\Di;

/**
 * 日志代理
 */
class LoggerProxy
{
    private static $instance;
    private static $logger;

    public static function instance()
    {
        self::$logger = Di::getDefault()->getShared('logger');
        if (self::$instance == null) {
            self::$instance = new LoggerProxy();
        }

        return self::$instance;
    }

    public function __call($func, $arguments)
    {
        if (!empty($arguments)) {
            if (is_array($arguments[0])) {
                $arguments[0] = json_encode($arguments[0]);
            }
        }

        if (defined('CURRENT_TASK')) {
            $arguments[0] = "[" . CURRENT_TASK . "]" . $arguments[0];
        }

        return call_user_func_array(array(self::$logger, $func), $arguments);
    }
}
