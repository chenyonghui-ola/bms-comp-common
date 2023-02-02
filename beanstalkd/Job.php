<?php

namespace Imee\Comp\Common\Beanstalkd;

class Job
{
    protected $_server;
    protected $_id;
    protected $_body;

    public function __construct($server, $id, $body)
    {
        $this->_server = $server;
        $this->_id = $id;
        $this->_body = $body;
    }

    public function __get($name)
    {
        $key = '_' . $name;
        return isset($this->{$key}) ? $this->{$key} : null;
    }

    public function delete()
    {
        return $this->_server->delete($this->_id);
    }

    //把当前job重新放回队列的ready里面，推迟60s
    public function release($pri = 1024, $delay = 60)
    {
        return $this->_server->release($this->_id, $pri, $delay);
    }

    public function restore($data, $priority = 1024, $delay = 0, $ttl = 60)
    {
        $this->delete();
        return $this->_server->put($priority, $delay, $ttl, $this->serialize($data));
    }

    //重新设置当前job的开始过期时间
    public function touch()
    {
        return $this->_server->touch($this->_id);
    }

    //
    public function bury($pri = 1024)
    {
        return $this->_server->bury($this->_id);
    }

    public function dispose()
    {
        $this->_server = null;
        $this->_body = null;
    }

    public function __destruct()
    {
        $this->dispose();
    }

    //igbinary_serialize
    protected function serialize($val)
    {
        if (is_numeric($val)) return $val;
        return serialize($val);
    }
}