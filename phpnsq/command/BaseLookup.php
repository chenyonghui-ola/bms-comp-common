<?php

namespace Imee\Comp\Common\PhpNsq\Command;

use Imee\Comp\Common\Sdk\SdkBase;
use Imee\Comp\Common\PhpNsq\PhpNsq;
use Imee\Comp\Common\EventLoop\Factory;

require_once ROOT . DS . 'comp/common/eventloop/Factory.php';

class BaseLookup
{
    private $_phpnsq;
    private $_loop;
    private $_timer = null;
    private $_lookupTimer = null;

    private $__onMessage;
    private $__onException;
    private $__onExit;

    private $_lookupAddr;

    public function __construct($lookupAddr, $onMessage, $onException = null, $onExit = null)
    {
        $this->__onMessage = $onMessage;
        $this->__onException = $onException;
        $this->__onExit = $onExit;
        $this->_lookupAddr = $lookupAddr;
        $this->_loop = Factory::create();
        $this->_maxMemory = 1024 * 1024 * 128; //128MB
    }

    private $_warnDuration = 0.1;

    public function setWarn($duration)
    {
        $this->_warnDuration = $duration;
    }

    //从lookup获取nsd
    private function getNsdFromLookup($topic)
    {
        $replace = array(
            '10.80.153.177:4150' => '172.16.0.179:4250',
            '10.31.52.45:4150'   => '172.16.0.179:4050',
            '10.31.52.45:4152'   => '172.16.0.179:4052',
            '10.31.52.45:4154'   => '172.16.0.179:4054',
            '10.31.52.45:4156'   => '172.16.0.179:4056',
            '10.31.52.45:4158'   => '172.16.0.179:4058',
            '10.81.45.178:4150'  => '172.16.0.179:4150',
            '10.81.45.178:4152'  => '172.16.0.179:4152',
            '10.81.45.178:4154'  => '172.16.0.179:4154',
            '10.81.45.178:4156'  => '172.16.0.179:4156',
        );
        $url = "http://{$this->_lookupAddr}/lookup?topic={$topic}";

        $http = new SdkBase(SdkBase::FORMAT_JSON, 1);
        $res = $http->httpRequest($url);

        echo "get url {$url}\n";
        print_r($res);
        echo "\n";
        if (isset($res['producers']) && is_array($res['producers']) && !empty($res['producers'])) {
            $ips = [];
            foreach ($res['producers'] as $item) {
                $ip = $item['broadcast_address'] . ':' . $item['tcp_port'];
                if (strpos($ip, "10.") === 0 && !isset($replace[$ip])) {
                    //在经典网络里，但是没在replace里面，那么有问题，直接崩掉
                    throw new \Exception("error with remote nsd ip " . $ip);
                }
                $ips[] = $replace[$ip] ?? $ip;
            }
            return $ips;
        }
        return [];
    }

    private $_lastNsds;
    private $_topic;
    private $_channel;
    private $_delayArray;

    public function run($topic, $channel, array $delayArray = array())
    {
        $this->_topic = $topic;
        $this->_channel = $channel;
        $this->_delayArray = $delayArray;
        while (true) {
            $ips = $this->getNsdFromLookup($this->_topic);
            if (empty($ips)) {
                echo "wait for topic 10s\n";
                sleep(10);
            } else {
                break;
            }
        }
        $this->runWithNsd($ips);
    }

    private function runWithNsd($ips)
    {
        print_r($ips);
        $this->_lastNsds = $ips;
        $this->_phpnsq = new PhpNsq($ips);
        $this->_loop->addSignal(SIGINT, array($this, '_Signal'));
        $this->_loop->addSignal(SIGTERM, array($this, '_Signal'));
        $this->_timer = $this->addPeriodicTimer(3, array($this, '_onMemoryCheck'));
        $this->_lookupTimer = $this->addPeriodicTimer(60, array($this, '_onLookupCheck'));
        $this->_nsdTimer = $this->addPeriodicTimer(10, array($this, '_onNsdCheck'));
        $this->_phpnsq->setWarn($this->_warnDuration);
        $this->_phpnsq->setTopic($this->_topic)
            ->setChannel($this->_channel)
            ->setReTry($this->_delayArray)
            ->subscribe($this, array($this, '_onMessage'), array($this, '_onException'));
        $this->_loop->run();
        echo date('Y-m-d H:i:s') . " => exit safe\n";
    }

    private $_heartbeatError = 0;

    public function _onNsdCheck()
    {
        //检测已经连接中NSD心跳
        $min = $this->_phpnsq->getMinLastHeartbeat();
        if ($min > -1) {
            $diff = time() - $min;
            if ($diff > 30) { //最多允许两次没有心跳
                $this->_heartbeatError++;
                echo "nsd Heartbeat error, diff = {$diff}, error = {$this->_heartbeatError}\n";
                if ($this->_heartbeatError >= 2) $this->_onException(null, new \Exception("error with nsd Heartbeat"));
                return;
            } else {
                echo "nsd Heartbeat ok, diff = {$diff}\n";
            }
        }
        $this->_heartbeatError = 0;
    }

    public function _onLookupCheck()
    {
        $ips = $this->getNsdFromLookup($this->_topic);
        if (empty($ips)) {
            echo "error with from lookup\n";
            return;
        }

        //比对前后变化, 只管新增
        $len = count($ips);
        for ($i = 0; $i < $len; $i++) {
            if (!in_array($ips[$i], $this->_lastNsds)) {
                $this->_phpnsq->addNsd($ips[$i]);
                $this->_lastNsds[] = $ips[$i];
            }
        }
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

    private $_processMessages = array();
    private $_processNum = 0;

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
        $messageId = $tunnel->getConfig()->host . ":" . $tunnel->getConfig()->port . ":" . $message->getId();
        if (isset($this->_processMessages[$messageId])) {
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
        $r = call_user_func($this->__onMessage, $body, $messageId, $message->getTimestamp());
        if ($r === false) {
            //已经处理完成，记在内存中，防止部分程序不处理消息幂等性问题
            //只是简单处理，增大可靠性，如果消息太乱，不在此考虑里面
            //php 的 map 是有序的
            $this->_processNum++;
            $this->_processMessages[$messageId] = true;
            if ($this->_processNum > 100) {
                //删除前50个
                while ($this->_processNum > 50) {
                    $this->_processNum--;
                    array_shift($this->_processMessages);
                }
            }
        }
        return $r;
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

    private $_maxMemory = 0;

    public function setMaxMemory($memory)
    {
        $this->_maxMemory = $memory;
    }

    public function _onMemoryCheck()
    {
        $memory = memory_get_usage();
        if ($memory > $this->_maxMemory && $this->_phpnsq != null) {
            echo date('Y-m-d H:i:s') . " => memory usage too big " . intval($memory / 1024 / 1024) . "MB\n";
            $this->_phpnsq->setExit();
            return;
        }
        $formatted = number_format($memory / 1024, 3) . 'K';
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
