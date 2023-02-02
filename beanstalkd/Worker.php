<?php

namespace Imee\Comp\Common\Beanstalkd;

class Worker extends Beanstalkd
{
    public function __construct()
    {
        parent::__construct();
    }

    //It's block
    public function get($timeout = null)
    {
        $data = $this->_server->reserve($timeout);
        if ($data === false) return false;
        return new Job($this->_server, $data['id'], $this->unserialize($data['body']));
    }

    public function watch($name)
    {
        return $this->_server->watch($name);
    }

    //for producers
    public function choose($name)
    {
        return $this->_server->choose($name);
    }

    //Remove the named tube from the watch list.
    public function ignore($name)
    {
        return $this->_server->ignore($name);
    }

    //move state buried to ready
    public function kick($num)
    {
        return $this->_server->kick($num);
    }
}