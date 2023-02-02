<?php

namespace Imee\Comp\Common\Beanstalkd;

use Phalcon\Di;

class BeanstalkdPool
{
    private static $_conn = array();

    public static function conn($key, $usePool)
    {
        if ($usePool) {
            if (!isset(self::$_conn[$key])) {
                $options = Di::getDefault()->getShared('config')->{$key};
                $options['persistent'] = true;
                $options['timeout'] = 1;
                $options['name'] = $key;
                self::$_conn[$key] = new SocketBeanstalkd($options);
            }
            return self::$_conn[$key];
        } else {
            $options = Di::getDefault()->getShared('config')->{$key};
            $options['persistent'] = false;
            $options['timeout'] = 1;
            $options['name'] = $key;
            return new SocketBeanstalkd($options);
        }
    }

    public static function close($key)
    {
        if (isset(self::$_conn[$key])) {
            self::$_conn[$key]->__destruct();
            unset(self::$_conn[$key]);
        }
    }
}

class Beanstalkd
{
    protected $_server;
    private $_name;
    private $_lastTube = null;
    private $_usePool = true;

    public function __construct($key = 'beanstalk', $usePool = true)
    {
        $this->_name = $key;
        $this->_usePool = $usePool;
        $this->_server = BeanstalkdPool::conn($this->_name, $this->_usePool);
    }

    public function connect($num = 1)
    {
        if (!$this->_server) $this->_server = BeanstalkdPool::conn($this->_name, $this->_usePool);
        while ($num > 0) {
            $num--;
            $res = $this->_server->connect();
            if ($res === true) return true;
        }
        return false;
    }

    public function delete($id)
    {
        return $this->_server->delete($id);
    }

    //return false or jobid
    //priority 数字越大，优先级越低
    public function set($data, $priority = 1024, $delay = 0, $ttl = 60)
    {
        $num = 0;
        while ($num < 3) {
            $r = $this->_server->put($priority, $delay, $ttl, $this->serialize($data));
            if ($r !== false) return $r;
            Di::getDefault()->getShared('logger')->error("[Beanstalkd][{$this->_lastTube}|{$priority}|{$delay}|{$ttl}|" . json_encode($data) . "]Set Error " . var_export($this->_server->errors(), true));
            $num++;
        }
        return false;
    }

    public function error()
    {
        return $this->_server->errors();
    }

    //igbinary_serialize
    protected function serialize($val)
    {
        if (is_numeric($val)) return $val;
        return serialize($val);
    }

    //igbinary_unserialize
    protected function unserialize($val)
    {
        if (is_numeric($val)) return $val;
        return unserialize($val);
    }

    public function close($force = false)
    {
        if ($this->_usePool) {
            if ((!IS_CLI || $force) && $this->_server) {
                BeanstalkdPool::close($this->_name);
                $this->_server = null;
            }
        } else {
            if ($this->_server) {
                $this->_server->__destruct();
                $this->_server = null;
            }
        }
    }

    //for producers
    public function choose($name)
    {
        $this->_lastTube = $name;
        return $this->_server->choose($name);
    }

    public function __destruct()
    {
        if (!IS_CLI) $this->close(true);
    }
}