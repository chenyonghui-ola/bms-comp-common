<?php

namespace Imee\Comp\Common\Rpc\Middleware;

use Phalcon\Di;
use Psr\Http\Message\RequestInterface;

class Header
{
    public function __invoke(callable $handler)
    {
        return function (
            RequestInterface $request,
            array $options
        ) use ($handler) {
            $uuid = Di::getDefault()->getShared('uuid');
            $request = $request->withHeader('X-Request-Id', $uuid);
            return $handler($request, $options);
        };
    }
}