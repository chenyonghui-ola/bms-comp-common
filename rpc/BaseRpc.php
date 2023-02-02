<?php

namespace Imee\Comp\Common\Rpc;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Phalcon\Di;
use Imee\Comp\Common\Rpc\Middleware\Header;
use Imee\Comp\Common\Rpc\Middleware\Logger;
use Imee\Comp\Common\Rpc\Middleware\Retry;
use Imee\Comp\Common\Rpc\Middleware\Uri;
use Imee\Comp\Common\Rpc\Utils\Str;
use GuzzleHttp\Promise;

abstract class BaseRpc
{
    public $apiList = [];

    private $retryOptions = [
        'max'   => 0,
        'delay' => 100,
    ];

    private $rpcOptions = [
        'connect_timeout' => 2.0,
        'timeout'         => 5.0,
    ];

    abstract protected function serviceConfig();

    abstract protected function decode(Response $response = null, int $code = 200);

    protected function prepareOptions($apiName = null)
    {
        $apiOptions = $serviceOptions = [];

        $serviceConfig = $this->serviceConfig();
        $apiConfig = $this->apiList($apiName);

        if (isset($serviceConfig['options'])) {
            $serviceOptions = $serviceConfig['options'];
        }

        if (isset($apiConfig['options'])) {
            $apiOptions = $apiConfig['options'];
        }

        return $apiOptions + $serviceOptions + $this->rpcOptions;
    }

    protected function apiList($apiName = ''): array
    {
        if (!isset($this->apiList[$apiName])) {
            throw new InvalidApiNameException('Api ' . $apiName . ' is not set');
        }

        return $this->apiList[$apiName];
    }

    public function getConfig($apiName)
    {
        $serConfig = $this->serviceConfig();
        $apiConfig = $this->apiList($apiName);
        $handler = $this->getClientHandlerStack($apiName);

        return array($serConfig, $apiConfig, $handler);
    }

    /**
     * Do http call to remote service
     *
     * @param $apiName
     * @param array $options
     * @return mixed
     * @throws \InvalidArgumentException
     * @throws InvalidApiNameException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function call($apiName, array $options = [])
    {
        $serviceConfig = $this->serviceConfig();
        if (!isset($serviceConfig['domain']) || !isset($serviceConfig['host'])) {
            throw new InvalidArgumentException('ApiResource is not defined');
        }

        $domain = $serviceConfig['domain'];
        $host = $serviceConfig['host'];

        $apiConfig = $this->apiList($apiName);

        if (!isset($apiConfig['method']) || !isset($apiConfig['path'])) {
            throw new InvalidApiNameException('Api name "$apiName" is not defined');
        }
        $method = $apiConfig['method'];
        $path = $apiConfig['path'];

        $clientOptions = $this->prepareOptions($apiName);
        $clientOptions['base_uri'] = $domain;
        $clientOptions['handler'] = $this->getClientHandlerStack($apiName);

        $clientOptions = array_replace_recursive($clientOptions, $options);
        $client = new Client($clientOptions);
        $request = new Request($method, $path, ['Host' => $host]);

        try {
            $response = $client->send($request);
            $statusCode = $response->getStatusCode();
        } catch (ClientException|ServerException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
        } catch (ConnectException $e) {
            $response = null;
            $statusCode = 0;
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
            } else {
                $response = null;
                $statusCode = 0;
            }
        } catch (\Exception $e) {
            $response = null;
            $statusCode = 0;
            $logger = Di::getDefault()->getShared('logger');
            $line = '[RPC] ' . $request->getMethod() . ' ' . $request->getRequestTarget()
                . '|host:' . $host
                . '|options:' . json_encode($options, JSON_UNESCAPED_UNICODE)
                . '|exception:' . Str::exceptionToStringWithoutLF($e);
            $logger->error($line);
        }

        return $this->decode($response, $statusCode);
    }

    private function getClientHandlerStack($apiName)
    {
        $serviceConfig = $this->serviceConfig();
        $retryOptions = $serviceConfig['retry'] ?? $this->retryOptions;

        $apiConfig = $this->apiList($apiName);
        if (isset($apiConfig['retry'])) {
            $retryOptions = $apiConfig['retry'] + $retryOptions;
        }
        $handlerStack = HandlerStack::create();
        $retryMiddleware = Middleware::retry(Retry::decider($retryOptions['max']), Retry::delay($retryOptions['delay']));
        $middlewares = [
            $retryMiddleware,
            new Uri(),
            new Header(),
            new Logger(),
        ];

        foreach ($middlewares as $middleware) {
            $handlerStack->push($middleware);
        }

        return $handlerStack;
    }

    /**
     * Batch request
     * @param $apiName
     * @param array $queryData
     * @param string $contentType
     * @return array
     * @throws InvalidApiNameException
     */
    public function callBatch($apiName, array $queryData = [], string $contentType = 'query')
    {
        $serviceConfig = $this->serviceConfig();
        if (!isset($serviceConfig['domain']) || !isset($serviceConfig['host'])) {
            throw new InvalidArgumentException('ApiResource is not defined');
        }
        $apiConfig = $this->apiList($apiName);
        if (!isset($apiConfig['method']) || !isset($apiConfig['path'])) {
            throw new InvalidApiNameException('Api name "$apiName" is not defined');
        }

        // build headers
        $clientOptions = $this->prepareOptions($apiName);
        $clientOptions['base_uri'] = $serviceConfig['domain'];
        $clientOptions['handler'] = $this->getClientHandlerStack($apiName);
        $client = new Client($clientOptions);
        $promises = [];
        foreach ($queryData as $key => $params) {
            $promises[$key] = $client->requestAsync($apiConfig['method'], $apiConfig['path'], [
                'headers'    => ['Host' => $serviceConfig['host']],
                $contentType => $params,
            ]);
        }

        $promiseResults = Utils::settle($promises)->wait();
        $results = [];
        foreach ($promiseResults as $requestKey => $promiseResult) {
            if ($promiseResult['state'] === Promise\PromiseInterface::FULFILLED) {
                $response = $promiseResult['value'];
                $httpCode = $response->getStatusCode();
            } elseif ($promiseResult['state'] === Promise\PromiseInterface::REJECTED) {
                $exception = $promiseResult['reason'];
                if ($exception->hasResponse()) {
                    $response = $exception->getResponse();
                    $httpCode = $response->getStatusCode();
                } else {
                    $response = null;
                    $httpCode = 500;
                }
            } else {
                $response = null;
                $httpCode = 250;
            }
            $results[$requestKey] = $this->decode($response, $httpCode);
        }
        return $results;
    }
}