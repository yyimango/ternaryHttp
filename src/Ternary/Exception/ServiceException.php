<?php

namespace Ternary\Exception;

use Exception;

class ServiceException extends Exception
{
    private $url;

    private $parameter;

    private $response;

    public function __construct(string $url, array $parameter, $response, string $message = "", int $code = 200)
    {
        $this->url = $url;
        $this->parameter = $parameter;
        $this->response = $response;

        parent::__construct($message, $code);
    }

    public function getContext(): array
    {
        return ['url' => $this->url, 'parameter' => $this->parameter, 'response' => $this->response];
    }

}