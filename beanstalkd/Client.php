<?php

namespace Imee\Comp\Common\Beanstalkd;

class Client extends Beanstalkd
{
    public function __construct($key = 'beanstalk', $usePool = true)
    {
        parent::__construct($key, $usePool);
    }
}