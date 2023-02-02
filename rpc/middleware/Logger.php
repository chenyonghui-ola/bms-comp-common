<?php

namespace Imee\Comp\Common\Rpc\Middleware;

use Imee\Comp\Common\Rpc\Utils\ContentType;
use Imee\Comp\Common\Rpc\Utils\Str;
use Imee\Comp\Common\Rpc\Utils\Timer;
use Phalcon\Di;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Logger
{
    /**
     * @var \GuzzleHttp\Promise\FulfilledPromise|\GuzzleHttp\Promise\RejectedPromise;
     */
    private $promise;

    public function __invoke(callable $handler)
    {
        $logger = Di::getDefault()->getShared('logger');

        return function (RequestInterface $request, array $options)
        use ($handler, $logger) {
            Timer::start('rpc');
            $this->promise = $handler($request, $options);
            $cost = Timer::stop('rpc');

            return $this->promise->then(
                static function (ResponseInterface $response) use ($request, $options, $cost, $logger) {
                    $req = self::logRequest($request);
                    $res = self::logResponse($response, $options);
                    $log = array_merge($req, $res, ['cost:' . $cost]);
                    $line = '[RPC] ' . implode('|', $log);
                    if ((int)$response->getStatusCode() >= 500) {
                        $logger->error($line);
                    } else if ((int)$response->getStatusCode() >= 300) {
                        $logger->warning($line);
                    } else {
                        $logger->info($line);
                    }

                    return $response;
                },
                static function ($reason) use ($request, $cost, $logger) {
                    if (!($reason instanceof \Exception)) {
                        throw new \RuntimeException(
                            'Guzzle\Middleware\Logger: unknown error reason: '
                            . (is_object($reason) ? get_class($reason) : (string)$reason)
                        );
                    }

                    $req = self::logRequest($request);
                    $log = array_merge($req, ['cost:' . $cost]);
                    $line = '[RPC] ' . implode('|', $log);
                    $line .= '|exception: ' . Str::exceptionToStringWithoutLF($reason);
                    $logger->error($line);

                    throw $reason;
                }
            );
        };
    }

    protected static function logRequest(RequestInterface $r): array
    {
        return [
            'curl_cmd:' . Str::formatCurlCommand($r)
        ];
    }

    protected static function logResponse(ResponseInterface $response, $options): array
    {
        $contentType = $response->getHeaderLine('Content-Type');
        $data = [
            'response_status_code:' . $response->getStatusCode(),
        ];

        $responseBody = '';
        if (ContentType::isReadable($contentType)) {
            $responseBody = (string)$response->getBody();
        }
        $data[] = 'response_body:' . $responseBody;

        return $data;
    }
}