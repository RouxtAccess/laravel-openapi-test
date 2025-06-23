<?php

namespace RouxtAccess\OpenApi\Testing\Laravel\Response;

use Symfony\Component\HttpFoundation\Response;

class LaravelResponse
{
    protected $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public function getHeaders()
    {
        return $this->response->headers->all();
    }

    public function getStatusCode()
    {
        return $this->response->getStatusCode();
    }

    public function getBody()
    {
        return $this->response->getContent();
    }
}
