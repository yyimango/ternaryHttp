<?php

namespace Ternary\Http;

class TernaryHttp
{
    static function __callStatic($method, $args)
    {
        return PendingRequest::new()->{$method}(...$args);
    }
}