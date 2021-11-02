<?php

namespace Ternary\Http;

use GuzzleHttp\Exception\RequestException;
use Ternary\Exception\ServiceException;
use Ternary\Traits\Macroable;

class PendingRequest
{
    use Macroable;

    private $checkResponse;

    private $beforeSendingCallbacks;

    private $bodyFormat;

    private $options;

    function __construct()
    {
        $this->beforeSendingCallbacks = collect();
        $this->bodyFormat = 'json';
        $this->checkResponse = true;
        $this->options = [
            'http_errors'     => false,
            'connect_timeout' => 5,
            'timeout'         => 30
        ];
    }

    static function new(...$args)
    {
        return new self(...$args);
    }

    function getConcurrent(...$args)
    {
        return $this->buildClient()->getAsync(...$args);
    }

    function postConcurrent(...$args)
    {
        return $this->buildClient()->postAsync(...$args);
    }

    function withoutCheckResponse()
    {
        return $this->tap($this, function ($request) {
            return $this->checkResponse = false;
        });
    }

    function isCheckResponse()
    {
        return $this->checkResponse;
    }

    function withoutRedirecting()
    {
        return $this->tap($this, function ($request) {
            return $this->options = array_merge_recursive($this->options, ['allow_redirects' => false,]);
        });
    }

    function setConnectTimeout(int $timeout)
    {
        $this->options['connect_timeout'] = $timeout;
        return $this;
    }

    function setRequestTimeout(int $timeout)
    {
        $this->options['timeout'] = $timeout;
        return $this;
    }

    function withoutVerifying()
    {
        return $this->tap($this, function ($request) {
            return $this->options = array_merge_recursive($this->options, ['verify' => false,]);
        });
    }

    function asJson()
    {
        return $this->bodyFormat('json')->contentType('application/json');
    }

    function asFormParams()
    {
        return $this->bodyFormat('form_params')->contentType('application/x-www-form-urlencoded');
    }

    function asMultipart()
    {
        return $this->bodyFormat('multipart');
    }

    function bodyFormat($format)
    {
        return $this->tap($this, function ($request) use ($format) {
            $this->bodyFormat = $format;
        });
    }

    function contentType($contentType)
    {
        return $this->withHeaders(['Content-Type' => $contentType]);
    }

    function accept($header)
    {
        return $this->withHeaders(['Accept' => $header]);
    }

    function withHeaders($headers)
    {
        return $this->tap($this, function ($request) use ($headers) {
            return $this->options = array_merge_recursive($this->options, ['headers' => $headers,]);
        });
    }

    function withBasicAuth($username, $password)
    {
        return $this->tap($this, function ($request) use ($username, $password) {
            return $this->options = array_merge_recursive($this->options, ['auth' => [$username, $password],]);
        });
    }

    function withDigestAuth($username, $password)
    {
        return $this->tap($this, function ($request) use ($username, $password) {
            return $this->options = array_merge_recursive($this->options, ['auth' => [$username, $password, 'digest'],]);
        });
    }

    function beforeSending($callback)
    {
        return $this->tap($this, function () use ($callback) {
            $this->beforeSendingCallbacks[] = $callback;
        });
    }

    function get($url, $queryParams = [])
    {
        return $this->send('GET', $url, ['query' => $queryParams,]);
    }

    function post($url, $params = [])
    {
        return $this->send('POST', $url, ['body' => json_encode($params, JSON_UNESCAPED_SLASHES)]);
    }

    function patch($url, $params = [])
    {
        return $this->send('PATCH', $url, [$this->bodyFormat => $params,]);
    }

    function put($url, $params = [])
    {
        return $this->send('PUT', $url, [$this->bodyFormat => $params,]);
    }

    function delete($url, $params = [])
    {
        return $this->send('DELETE', $url, [$this->bodyFormat => $params,]);
    }

    function send($method, $url, $options)
    {
        $isCheckResponse = $this->isCheckResponse();
        return new TernaryResponse($this->buildClient()->requestAsync(
            $method,
            $url,
            $this->mergeOptions(['query' => $this->parseQueryParams($url)], $options)
        )->then(
            function ($response) use ($url, $options, $isCheckResponse) {
                $result = json_decode($response->getBody(), true);
                if ($isCheckResponse &&
                    ((isset($result['success']) && $result['success'] != true) ||
                        (isset($result['status']) && $result['status'] != 200))
                ) {
                    throw new ServiceException(
                        $url,
                        $options,
                        $response,
                        $result['error']['message'] ?? '未知错误',
                        $result['error']['code'] ?? 10001
                    );
                }
                return $response;
            },
            function (RequestException $e) {
                throw $e;
            }
        )->wait());
    }

    function buildClient()
    {
        return new \GuzzleHttp\Client(['handler' => $this->buildHandlerStack()]);
    }

    function buildHandlerStack()
    {
        return $this->tap(\GuzzleHttp\HandlerStack::create(), function ($stack) {
            $stack->push($this->buildBeforeSendingHandler());
        });
    }

    function buildBeforeSendingHandler()
    {
        return function ($handler) {
            return function ($request, $options) use ($handler) {
                return $handler($this->runBeforeSendingCallbacks($request), $options);
            };
        };
    }

    function runBeforeSendingCallbacks($request)
    {
        return $this->tap($request, function ($request) {
            $this->beforeSendingCallbacks->each->__invoke(new TernaryRequest($request));
        });
    }

    function mergeOptions(...$options)
    {
        return array_merge_recursive($this->options, ...$options);
    }

    function parseQueryParams($url)
    {
        return $this->tap([], function (&$query) use ($url) {
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
        });
    }
}