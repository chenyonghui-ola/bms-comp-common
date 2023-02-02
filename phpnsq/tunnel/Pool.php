<?php

namespace Imee\Comp\Common\PhpNsq\Tunnel;

class Pool
{
    private $pool = [];

    public function __construct($config)
    {
        foreach ($config as $value) {
            $addr = explode(":", $value);
            $this->addTunnel(new Tunnel(
                new Config($addr[0], $addr[1])
            ));
        }
    }

    public function addTunnel(Tunnel $tunnel)
    {
        array_push($this->pool, $tunnel);
        $this->_length = -1;
        return $this;
    }

    public function addTunnelByIp($ip)
    {
        $addr = explode(":", $ip);
        $tunnel = new Tunnel(
            new Config($addr[0], $addr[1])
        );
        $this->addTunnel($tunnel);
        return $tunnel;
    }

    private $_index = -1;
    private $_length = -1;
    public function getTunnel()
    {
        if($this->_length == -1){
            $this->_length = count($this->pool);
        }
        //首次获取时，从nsd中随机一个
        //下次获取时，获取上次中的下一个
        if($this->_index == -1){
            $this->_index = array_rand($this->pool);
        }else{
            $this->_index = ($this->_index + 1) % $this->_length;
        }
        return $this->pool[$this->_index];
    }

    public function getAll()
    {
        return $this->pool;
    }
}
