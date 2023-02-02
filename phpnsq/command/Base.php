<?php

namespace Imee\Comp\Common\PhpNsq\Command;

use Imee\Comp\Common\EventLoop\Factory;

require_once ROOT . DS . 'comp/common/eventloop/Factory.php';

class Base
{
    private $_phpnsq;
    private $_loop;
    private $_timer = null;

    private $__onMessage;
    private $__onException;
    private $__onExit;

    public function __construct($config, $onMessage, $onException = null, $onExit = null)
    {
        $this->__onMessage = $onMessage;
        $this->__onException = $onException;
        $this->__onExit = $onExit;
        $this->_loop = Factory::create();
        $this->_phpnsq = new PhpNsq($config);
    }

    public function run($topic, $channel, array $delayArray = array())
    {
        $this->_loop->addSignal(SIGINT, array($this, '_Signal'));
        $this->_loop->addSignal(SIGTERM, array($this, '_Signal'));
        $this->_timer = $this->addPeriodicTimer(3, array($this, '_onMemoryCheck'));
        $this->_phpnsq->setTopic($topic)
            ->setChannel($channel)
            ->setReTry($delayArray)
            ->subscribe($this, array($this, '_onMessage'), array($this, '_onException'));
        $this->_loop->run();
        echo date('Y-m-d H:i:s') . " => exit safe\n";
    }

    public function nsq()
    {
        return $this->_phpnsq;
    }

    public function __destruct()
    {

    }

    public function _Signal($signo)
    {
        echo date('Y-m-d H:i:s') . " => sig_handler {$signo}\n";
        if ($signo == SIGTERM || $signo == SIGINT) {
            //wait to exit
            $this->_phpnsq->setExit();
        }
    }

    public function _onMessage($tunnel, $message)
    {
        if ($message == null) {
            $this->_loop->stop();
            echo date("Y-m-d H:i:s") . " loop stop\n";
            if ($this->__onExit != null) {
                call_user_func($this->__onExit);
            }
            return;
        }
        if ($this->__onMessage == null) {
            return false;
        }
        $body = $message->getBody();
        if (is_array($body) && isset($body['__exit']) && is_numeric($body['__exit'])) {
            //尽量让多进程消费的每个进程都能收到一个消息
            //有几个进程你就发送几次消息
            if (intval($body['__exit']) > 0) sleep(intval($body['__exit']));
            $this->_loop->stop();
            if ($this->__onExit != null) {
                call_user_func($this->__onExit);
            }
            return false;
        }
        return call_user_func($this->__onMessage, $body, $message->getId(), $message->getTimestamp());
    }

    public function _onException($tunnel, $exception)
    {
        if ($this->__onException != null) {
            call_user_func($this->__onException, $exception);
        } else {
            echo date("Y-m-d H:i:s") . " wait for exit\n";
            sleep(3);
            exit(255);
        }
    }

    public function _onMemoryCheck()
    {
        $memory = memory_get_usage() / 1024;
        $formatted = number_format($memory, 3) . 'K';
        echo date("Y-m-d H:i:s") . " ### Current memory usage: {$formatted} ###\n";
    }

    public function addReadStream($socket, $closure)
    {
        $this->_loop->addReadStream($socket, $closure);
        return $this;
    }

    public function addPeriodicTimer($interval, $closure)
    {
        $this->_loop->addPeriodicTimer($interval, $closure);
        return $this;
    }

    public function addTimer($interval, $closure)
    {
        return $this->_loop->addPeriodicTimer($interval, $closure);
    }

    public function cancelTimer($timer)
    {
        if (!$timer) return;

        $this->_loop->cancelTimer($timer);
    }
}
