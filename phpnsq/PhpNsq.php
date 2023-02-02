<?php

namespace Imee\Comp\Common\PhpNsq;

use Imee\Comp\Common\PhpNsq\Tunnel\Pool;
use Imee\Comp\Common\PhpNsq\Tunnel\Tunnel;
use Imee\Comp\Common\PhpNsq\Wire\Reader;
use Imee\Comp\Common\PhpNsq\Wire\Writer;

class PhpNsq
{
    private $pool;
    private $channel;
    private $topic;
    private $reader;
    private $exit = false;
    private $delayArray = array();

    public function __construct($nsq)
    {
        $this->reader = new reader();
        $this->pool = new Pool($nsq);
    }

    public function setChannel($channel)
    {
        $this->channel = $channel;

        return $this;
    }

    private $_warnDuration = 0.1;

    public function setWarn($duration)
    {
        $this->_warnDuration = $duration;
    }

    public function setTopic($topic)
    {
        $this->topic = $topic;

        return $this;
    }

    public function setReTry($delayArray)
    {
        $this->delayArray = $delayArray;
        return $this;
    }

    private $_lastHeartbeat = array();

    private function setLastHeartbeat(Tunnel $tunnel)
    {
        $config = $tunnel->getConfig();
        $key = $config->host . ":" . $config->port;
        $this->_lastHeartbeat[$key] = time();
    }

    public function getMinLastHeartbeat()
    {
        if (empty($this->_lastHeartbeat)) return -1;
        return min($this->_lastHeartbeat);
    }

    private function console($message)
    {
        echo "[" . date('Y-n-d H:i:s') . "]" . $message . "\n";
    }

    private function getTunnel()
    {
        return $this->pool->getTunnel();
    }

    public function addNsd($ip)
    {
        $tunnel = $this->pool->addTunnelByIp($ip);
        $this->subscribeTurn($this->_cmd, $tunnel, $this->_onMessageCallback, $this->_onExceptionCallback);
    }

    public function setExit()
    {
        echo "set exit\n";
        $this->exit = true;
        $all = $this->pool->getAll();
        foreach ($all as $tunnel) {
            $tunnel->write(Writer::rdy(0));
            $tunnel->write(Writer::cls());
        }
    }

    public function ping(&$errorMessage = null)
    {
        $all = $this->pool->getAll();
        foreach ($all as $tunnel) {
            try {
                $tunnel->write(Writer::nop());
            } catch (\Exception $e) {
                if (is_null($errorMessage)) {
                    $errorMessage = $e->getMessage();
                }
            }
        }
        return true;
    }

    public function close()
    {
        $all = $this->pool->getAll();
        foreach ($all as $tunnel) {
            try {
                $tunnel->close();
            } catch (\Exception $e) {

            }
        }
        return false;
    }

    private function _closeDelay()
    {
        $this->close();
    }

    private function readLastResponse($tunnel)
    {
        while (true) {
            $reader = $this->reader->bindTunnel($tunnel)->bindFrame();
            if ($reader->isHeartbeat()) {
                continue;
            } elseif ($reader->isOk()) {
                return true;
            } else {
                print_r($reader->print2());
                return false;
            }
        }
        return false;
    }

    private $_lastRemoteNsd;

    public function getLastSendNsd()
    {
        return $this->_lastRemoteNsd;
    }

    public function publish($message, &$errorMessage = null)
    {
        try {
            $tunnel = $this->getTunnel();
            $config = $tunnel->getConfig();
            $this->_lastRemoteNsd = $config->host . ":" . $config->port;
            $tunnel->write(Writer::pub($this->topic, serialize($message)));
            if ($this->readLastResponse($tunnel)) {
                return true;
            }
            if (is_null($errorMessage)) {
                $errorMessage = $this->reader->getError();
            }
        } catch (\Exception $e) {
            if (is_null($errorMessage)) {
                $errorMessage = $e->getMessage();
            }
        }
        $this->_closeDelay();
        return false;
    }

    public function publishMulti($bodies, &$errorMessage = null)
    {
        try {
            $data = array();
            foreach ($bodies as $val) {
                $data[] = serialize($val);
            }
            $tunnel = $this->getTunnel();
            $tunnel->write(Writer::mpub($this->topic, $data));
            if ($this->readLastResponse($tunnel)) {
                return true;
            }
            if (is_null($errorMessage)) {
                $errorMessage = $this->reader->getError();
            }
        } catch (\Exception $e) {
            if (is_null($errorMessage)) {
                $errorMessage = $e->getMessage();
            }
        }
        $this->_closeDelay();
        return false;
    }

    public function publishDefer($message, $deferTime, &$errorMessage = null)
    {
        try {
            $tunnel = $this->getTunnel();
            $tunnel->write(Writer::dpub($this->topic, $deferTime, serialize($message)));
            if ($this->readLastResponse($tunnel)) {
                return true;
            }
            if (is_null($errorMessage)) {
                $errorMessage = $this->reader->getError();
            }
        } catch (\Exception $e) {
            if (is_null($errorMessage)) {
                $errorMessage = $e->getMessage();
            }
        }
        $this->_closeDelay();
        return false;
    }

    private $_cmd;
    private $_onMessageCallback;
    private $_onExceptionCallback;

    public function subscribe($cmd, $onMessageCallback, $onExceptionCallback = null)
    {
        try {
            cli_set_process_title("php-cli-{$this->topic}-{$this->channel}");
        } catch (\Exception $e) {
            $this->console($e->getMessage());
        }
        $this->_cmd = $cmd;
        $this->_onMessageCallback = $onMessageCallback;
        $this->_onExceptionCallback = $onExceptionCallback;
        $all = $this->pool->getAll();
        foreach ($all as $tunnel) {
            $this->subscribeTurn($cmd, $tunnel, $onMessageCallback, $onExceptionCallback);
        }
    }

    private function subscribeTurn($cmd, $tunnel, $onMessageCallback, $onExceptionCallback = null)
    {
        try {
            $sock = $tunnel->getSock();

            $cmd->addReadStream($sock, function ($sock) use ($tunnel, $onMessageCallback, $onExceptionCallback) {
                $this->handleMessage($tunnel, $onMessageCallback, $onExceptionCallback);
            });

            $tunnel->write(Writer::identify(array(
                'output_buffer_size' => 1024 * 64,
                'hostname'           => $this->getLocalIp(),
                'user_agent'         => 'php' . PHP_VERSION . '-nsq/1.0.1',
                'heartbeat_interval' => 15 * 1000,
            )))->write(Writer::sub($this->topic, $this->channel))->write(Writer::rdy(1));

        } catch (\Exception $e) {
            $this->console($e->getMessage());
            if ($onExceptionCallback != null) {
                call_user_func($onExceptionCallback, $tunnel, $e);
            }
        }
    }

    private function getLocalIp()
    {
        $result = shell_exec("/sbin/ifconfig");
        if (preg_match_all("/inet (\d+\.\d+\.\d+\.\d+)/", $result, $match) !== 0) {
            foreach ($match[0] as $k => $v) {
                if ($match[1][$k] != "127.0.0.1" && $this->isIntranet($match[1][$k])) {
                    return $match[1][$k];
                }
            }
        }
        return "";
    }

    private function isIntranet($ip)
    {
        $ipLong = ip2long($ip);
        if (!$ipLong) {
            return false;
        }

        if (($ipLong & 0xff000000) === 0x0a000000) { //10.0.0.0 - 10.255.255.255
            return true;
        } elseif (($ipLong & 0xfff00000) === 0xac100000) { //172.16.0.0 - 172.31.255.255
            return true;
        } elseif (($ipLong & 0xffff0000) === 0xc0a80000) { //192.168.0.0 - 192.168.255.255
            return true;
        } else if (strpos($ip, '127.') == 0) {
            return true;
        }

        return false;
    }

    protected function handleMessage(Tunnel $tunnel, $onMessageCallback, $onExceptionCallback)
    {
        $begin = $this->microtimeFloat();
        $reader = $this->reader->bindTunnel($tunnel)->bindFrame();
        if ($reader->isHeartbeat()) {
            $this->setLastHeartbeat($tunnel);
            $tunnel->write(Writer::nop());
            $this->console('Ignoring "Heartbeat" frame in SUB loop');
        } elseif ($reader->isMessage()) {
            $msg = $reader->getMessage();
            $exception = null;
            $isOk = false;
            try {
                $r = call_user_func($onMessageCallback, $tunnel, $msg);
                $isOk = true;
            } catch (\Exception $e) {
                $exception = $e;
                $trace = $e->getTrace();
                $traceJson = array();
                foreach ($trace as $error) {
                    if (!isset($error['file'])) continue;
                    $traceJson[] = "{$error['file']}，{$error['line']}，{$error['class']}，{$error['function']}";
                }
                $this->console($e->getFile() . ' : ' . $e->getLine() . ' : ' . $e->getMessage() . ' : ' . json_encode($traceJson));
                $this->_touch($tunnel, $msg);
            }
            if ($isOk === true) {
                if ($r === false) {
                    //delete job
                    $tunnel->write(Writer::fin($msg->getId()));
                    $used_time = sprintf("%0.4f", $this->microtimeFloat() - $begin);
                    if ($used_time >= $this->_warnDuration) echo "[Warn]delete job {$msg->getId()}, used time {$used_time}, msg " . json_encode($msg->getBody()) . "\n";
                } else if ($r === 1) {
                    $this->_touch($tunnel, $msg);
                } else {
                    //restore, delay
                    $tunnel->write(Writer::fin($msg->getId()));
                    $this->_restore($tunnel, $msg);
                }
            }
            if ($onExceptionCallback != null && $exception != null) {
                call_user_func($onExceptionCallback, $tunnel, $exception);
            }
        } elseif ($reader->isOk()) {
            $this->console('Ignoring "OK" frame in SUB loop');
        } elseif ($reader->isResponse('CLOSE_WAIT')) {
            call_user_func($onMessageCallback, $tunnel, null);
            return;
        } elseif ($reader->isError()) {
            $this->console("Error received: " . $reader->getError());
            return;
        } else {
            $this->console("Error/unexpected frame received: ");
            if ($onExceptionCallback != null) {
                call_user_func($onExceptionCallback, $tunnel, new \Exception('Error/unexpected frame'));
            }
        }
    }

    private function _touch(Tunnel $tunnel, $msg)
    {
        if ($msg == null) return;
        $tunnel->write(Writer::touch($msg->getId()))
            ->write(Writer::req(
                $msg->getId(),
                $tunnel->getConfig()->get("defaultRequeueDelay")["default"]
            ));
    }

    private function _restore(Tunnel $tunnel, $msg)
    {
        if ($msg == null) {
            return;
        }
        $body = $msg->getBody();
        if (is_object($body)) {
            $num = isset($body->num) ? intval($body->num) : 0;
            if ($num >= count($this->delayArray)) {
                $this->console("delete job {$msg->getId()}");
                return;
            }
            $delay = $this->delayArray[$num];
            $body->num = $num + 1;
        } else if (is_array($body)) {
            $num = isset($body['num']) ? intval($body['num']) : 0;
            if ($num >= count($this->delayArray)) {
                $this->console("delete job {$msg->getId()}");
                return;
            }
            $delay = $this->delayArray[$num];
            $body['num'] = $num + 1;
        } else {
            return;
        }

        $this->console("delay {$delay}s {$msg->getId()} => " . json_encode($body));
        $tunnel->write(Writer::dpub($this->topic, $delay * 1000, serialize($body)));
        return;
    }

    public function send($tube, $data = null)
    {
        $this->setTopic($tube);
        $r = $this->publish($data);
        if ($r) {
            return $r;
        } else {
            throw new \Exception("error nsq send");
        }
    }

    private function microtimeFloat()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}
