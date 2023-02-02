<?php

namespace Imee\Comp\Common\Rpc\Middleware;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Phalcon\Di;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ConnectException;

class Retry
{
    public static function decider($maxTimes = 5)
    {
        $logger = Di::getDefault()->getShared('logger');

        return function ($retries, Request $request, Response $response = null, \Exception $exception = null) use ($maxTimes, $logger) {
            if ($retries >= $maxTimes) {
                return false;
            }

            if ($exception instanceof ConnectException) {
                $logger->info('Cannot connect to target ' . $request->getUri() . ' retrying for the ' . $retries . ' time');
                return true;
            }

            if ($response) {
                if ($response->getStatusCode() === 503) {
                    $logger->info('Server responses with status code ' . $response->getStatusCode() . ' retrying for the ' . $retries . ' time');
                    return true;
                }

                if ($response->getStatusCode() >= 500) {
                    $logger->info('Server responses with status code ' . $response->getStatusCode() . ' and this is a server error, won\'t retry');
                    return false;
                }

                if ($response->getStatusCode() >= 400) {
                    $logger->info('Server responses with status code ' . $response->getStatusCode() . ' and this is a client error, won\'t retry');
                    return false;
                }

                if ($response->getStatusCode() >= 300) {
                    $logger->info('Server responses with status code ' . $response->getStatusCode() . ' and this is not error, won\'t retry');
                    return false;
                }

                if ($response->getStatusCode() >= 200) {
                    $logger->info('Servers responses with status code ' . $response->getStatusCode() . ' and this is OK, won\'t retry');
                    return false;
                }
            }

            if (!($exception instanceof GuzzleException)) {
                $logger->info(
                    'Exception(' . (is_object($exception) ? get_class($exception) : (string)$exception) . ') is not instance of GuzzleException, won\'t retry');
                return false;
            }

            $logger->info('Retrying for the ' . $retries . ' time');

            return true;
        };
    }

    public static function delay($delay = 100)
    {
        return function ($numberOfRetries) use ($delay) {
            return $delay * $numberOfRetries;
        };
    }
}