<?php

namespace Imee\Comp\Common\PhpNsq\Utility;

class Logging
{
    private $dirname;

    public function __construct($name, $dirname)
    {
        $this->dirname = $dirname;
    }

    public function getHandler()
    {
        return $this->handler;
    }

    public function debug($msg, $context = null)
    {
       echo $msg . "\n";
    }

    public function info($msg, $context = null)
    {
        echo $msg . "\n";
    }

    public function warn($msg, $context = null)
    {
        echo $msg . "\n";
    }

    public function error($msg, $context = null)
    {
        echo $msg . "\n";
    }
}
