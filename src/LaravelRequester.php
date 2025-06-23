<?php

namespace RouxtAccess\OpenApi\Testing\Laravel;

use ByJG\ApiTools\AbstractRequester;
use ByJG\ApiTools\Exception\DefinitionNotFoundException;
use ByJG\ApiTools\Exception\GenericSwaggerException;
use ByJG\ApiTools\Exception\HttpMethodNotFoundException;
use ByJG\ApiTools\Exception\InvalidDefinitionException;
use ByJG\ApiTools\Exception\InvalidRequestException;
use ByJG\ApiTools\Exception\NotMatchedException;
use ByJG\ApiTools\Exception\PathNotFoundException;
use ByJG\ApiTools\Exception\RequiredArgumentNotFound;
use ByJG\ApiTools\Exception\StatusCodeNotMatchedException;
use ByJG\Util\Uri;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Str;
use JsonException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class LaravelRequester extends AbstractRequester
{
    protected ResponseInterface $response;
    protected TestCase $testCase;

    /**
     * @noinspection PhpMissingParentConstructorInspection
     * @noinspection MagicMethodsValidityInspection
     * @param  TestCase  $testCase
     * @throws RequestException
     */
    public function __construct(TestCase $testCase)
    {
        $this->withPsr7Request(new Request('GET', new Uri("/"), [], "[]"));
        $this->testCase = $testCase;
    }


    /**
     * @param  RequestInterface  $request
     * @return ResponseInterface
     * @throws JsonException
     */
    protected function handleRequest(RequestInterface $request) : ResponseInterface
    {
        $testResponse = $this->testCase->json(
            $request->getMethod(),
            (string) $request->getUri(),
            json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR),
            $this->getServerHeaders($request),
        );
        return $testResponse->baseResponse;
    }


    /**
     * @param  RequestInterface  $request
     * @return array
     */
    protected function getServerHeaders(RequestInterface $request) : array
    {
        // Convert headers to server headers
        return collect($request->getHeaders())->mapWithKeys(function ($value, $name) {
            return $this->formatServerHeader($name, $value);
        })->all();
    }

    /**
     * Format the header key/val for the server array
     * @param $name
     * @param $value
     * @return array
     */
    protected function formatServerHeader($name, $value) : array
    {
        $name = str_replace('-', '_', strtoupper($name));
        $name = $this->formatServerHeaderKey($name);
        if($name === "HTTP_AUTHORIZATION")
        {
            $value = array_shift($value);
        }

        return [$name => $value];
    }

    /**
     * Format the header name for the server array.
     *
     * @param string $name
     *
     * @return string
     */
    protected function formatServerHeaderKey(string $name): string
    {
        if ($name !== 'CONTENT_TYPE' && $name !== 'REMOTE_ADDR' && !Str::startsWith($name, 'HTTP_')) {
            return 'HTTP_'.$name;
        }

        return $name;
    }

    /**
     * @return ResponseInterface
     * @throws JsonException
     */
    public function send() : ResponseInterface
    {
        // Process URI based on the OpenAPI schema
        $uriSchema = new Uri($this->schema->getServerUrl());

        if (empty($uriSchema->getScheme())) {
            $uriSchema = $uriSchema->withScheme($this->psr7Request->getUri()->getScheme());
        }

        if (empty($uriSchema->getHost())) {
            $uriSchema = $uriSchema->withHost($this->psr7Request->getUri()->getHost());
        }

        $uri = $this->psr7Request->getUri()
            ->withScheme($uriSchema->getScheme())
            ->withHost($uriSchema->getHost())
            ->withPort($uriSchema->getPort())
            ->withPath($uriSchema->getPath() . $this->psr7Request->getUri()->getPath());

        if (!preg_match("~^{$this->schema->getBasePath()}~",  $uri->getPath())) {
            $uri = $uri->withPath($this->schema->getBasePath() . $uri->getPath());
        }

        $this->psr7Request = $this->psr7Request->withUri($uri);
        // Handle Request
        $this->response = $this->handleRequest($this->psr7Request);

        return $this->response;
    }

    /**
     * @return bool
     * @throws DefinitionNotFoundException
     * @throws GenericSwaggerException
     * @throws HttpMethodNotFoundException
     * @throws InvalidDefinitionException
     * @throws InvalidRequestException
     * @throws JsonException
     * @throws NotMatchedException
     * @throws PathNotFoundException
     * @throws RequiredArgumentNotFound
     */
    public function validateRequest(): bool
    {
        $requestBody = $this->psr7Request->getBody();
        $contentType = $this->psr7Request->getHeaderLine("content-type");
        $requestContents = null;

        // Get request body if exists
        if ($requestBody !== null) {
            $requestContents = $requestBody->getContents();

            if (empty($contentType) || str_contains($contentType, "application/json")) {
                $requestContents = json_decode($requestContents, true, 512, JSON_THROW_ON_ERROR);
            } elseif (str_contains($contentType, "multipart/")) {
                $requestContents = $this->parseMultiPartForm($contentType, $requestContents);
            } else {
                throw new InvalidRequestException("Cannot handle Content Type '{$contentType}'");
            }
        }

        // Check if the body is the expected before request
        $bodyRequestDef = $this->schema->getRequestParameters(
            $this->psr7Request->getUri()->getPath(), 
            $this->psr7Request->getMethod()
        );
        $bodyRequestDef->match($requestContents);
        
        return true;
    }

    /**
     * @param ResponseInterface $response
     * @param int|null $status
     * @return bool
     * @throws DefinitionNotFoundException
     * @throws GenericSwaggerException
     * @throws HttpMethodNotFoundException
     * @throws InvalidDefinitionException
     * @throws InvalidRequestException
     * @throws JsonException
     * @throws NotMatchedException
     * @throws PathNotFoundException
     * @throws StatusCodeNotMatchedException
     * @throws RequiredArgumentNotFound
     */
    public function validateResponse(ResponseInterface $response, ?int $status = null): bool
    {
        $responseHeaders = $response->getHeaders();
        $responseBodyStr = (string) $response->getBody();
        $responseBody = !empty($responseBodyStr) ? 
            json_decode($responseBodyStr, true, 512, JSON_THROW_ON_ERROR) : 
            null;
        $statusReturned = $response->getStatusCode();

        // Assert results
        if ($status !== null && $status !== $statusReturned) {
            throw new StatusCodeNotMatchedException(
                "Status code not matched: Expected {$status}, got {$statusReturned}",
                $responseBody
            );
        }

        $bodyResponseDef = $this->schema->getResponseParameters(
            $this->psr7Request->getUri()->getPath(),
            $this->psr7Request->getMethod(),
            $statusReturned
        );
        $bodyResponseDef->match($responseBody);

        foreach ($this->assertHeader as $key => $value) {
      if (!array_key_exists($key, $responseHeaders) || $responseHeaders[$key] !== $value) {
                throw new NotMatchedException(
                    "Header validation failed for '$key' with value '$value'",
                    $responseHeaders
                );
            }
        }

        if (!empty($responseBodyStr)) {
            foreach ($this->assertBody as $item) {
                if (!str_contains($responseBodyStr, $item)) {
                    throw new NotMatchedException("Body does not contain '{$item}'");
                }
            }
        }
        
        return true;
    }
}