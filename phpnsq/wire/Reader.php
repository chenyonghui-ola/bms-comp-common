<?php

namespace Imee\Comp\Common\PhpNsq\Wire;

use Imee\Comp\Common\PhpNsq\Message\Message;
use Imee\Comp\Common\PhpNsq\Tunnel\Tunnel;
use Imee\Comp\Common\PhpNsq\Utility\IntPacker;

class Reader
{
    const TYPE_RESPONSE = 0;
    const TYPE_ERROR    = 1;
    const TYPE_MESSAGE  = 2;

    const HEARTBEAT = "_heartbeat_";
    const OK        = "OK";

    private $tunnel;
    private $frame;

    public function __construct(Tunnel $tunnel = null)
    {
        $this->tunnel = $tunnel;
    }

    public function bindTunnel(Tunnel $tunnel)
    {
        $this->tunnel = $tunnel;

        return $this;
    }

    public function bindFrame()
    {
        $this->frame = null;
        $size = 0;
        $type = 0;
        try {
            $size = $this->readInt(4);
            $type = $this->readInt(4);
        } catch (\Exception $e) {
            echo "Error reading message frame [$size, $type] ({$e->getMessage()})\n";
            return $this;
           // throw new Exception("Error reading message frame [$size, $type] ({$e->getMessage()})");
        }

        $frame = [
            "size" => $size,
            "type" => $type,
        ];

        try {
            if (self::TYPE_RESPONSE == $type) {
                $frame["response"] = $this->readString($size - 4);
            } elseif (self::TYPE_ERROR == $type) {
                $frame["error"] = $this->readString($size - 4);
            }
        } catch (\Exception $e) {
            return $this;
            //throw new Exception("Error reading frame details [$size, $type] ({$e->getMessage()})");
        }

        $this->frame = $frame;

        return $this;
    }

    // DecodeMessage deserializes data (as []byte) and creates a new Message
    // message format:
    //  [x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x][x]...
    //  |       (int64)        ||    ||      (hex string encoded in ASCII)           || (binary)
    //  |       8-byte         ||    ||                 16-byte                      || N-byte
    //  ------------------------------------------------------------------------------------------...
    //    nanosecond timestamp    ^^                   message ID                       message body
    //                         (uint16)
    //                          2-byte
    //                         attempts
    public function getMessage()
    {
        if (null !== $this->frame && self::TYPE_MESSAGE == $this->frame["type"]) {
            return (new Message())->setTimestamp($this->unpackLong($this->tunnel->read(4), $this->tunnel->read(4)))
                ->setAttempts($this->readUInt16(2))
                ->setId($this->readString(16))
                ->setBody(unserialize($this->readString($this->frame["size"] - 30)))
                ->setDecoded();
        }

        return null;
    }

    public function print2()
    {
        var_dump($this->frame);
    }

    public function isNull()
    {
        return $this->frame == null;
    }

    public function isMessage()
    {
        return self::TYPE_MESSAGE == $this->frame["type"];
    }

    public function isHeartbeat()
    {
        return $this->isResponse(self::HEARTBEAT);
    }

    public function isOk()
    {
        return $this->isResponse(self::OK);
    }

    public function isError()
    {
        if($this->frame != null && self::TYPE_ERROR == $this->frame["type"]){
            return true;
        }
        return false;
    }

    public function getError()
    {
        if($this->frame != null
            && self::TYPE_ERROR == $this->frame["type"])
        return $this->frame["error"];
    }

    public function isResponse($response = null)
    {
        return isset($this->frame["response"])
            && self::TYPE_RESPONSE == $this->frame["type"]
            && (null === $response || $response === $this->frame["response"]);
    }

    private function readInt($size)
    {
        list(, $tmp) = unpack("N", $this->tunnel->read($size));

        return sprintf("%u", $tmp);
    }

    private function unpackLong($h, $l)
    {
        $hi = @unpack('N', $h);
        $lo = @unpack('N', $l);
        if (is_array($hi) && is_array($lo))
        {
            // workaround signed/unsigned braindamage in php
            $hi = sprintf("%u", $hi[1]);
            $lo = sprintf("%u", $lo[1]);
            return sprintf("%.3f", bcadd(bcmul($hi, "4294967296" ), $lo) / 1000000000);
        }
        else
        {
            return null;
        }
    }


    private function readInt64($size)
    {
        return IntPacker::int64($this->tunnel->read($size));
    }

    private function readUInt16($size)
    {
        return IntPacker::uInt16($this->tunnel->read($size));
    }

    private function readString($size)
    {
        $bin = $this->tunnel->read($size);
        $bytes = unpack("c{$size}chars", $bin);
        if(!is_array($bytes)){
            @file_put_contents("/home/log/debug.log", "------{$size}\n{$bin}\n------\n", FILE_APPEND);
            return serialize(array());
        }
        return implode(array_map("chr", $bytes));
    }
}
