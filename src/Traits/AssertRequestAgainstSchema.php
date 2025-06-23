<?php

namespace RouxtAccess\OpenApi\Testing\Laravel\Traits;

use ByJG\ApiTools\AbstractRequester;
use ByJG\ApiTools\Base\Schema;
use ByJG\ApiTools\Exception\DefinitionNotFoundException;
use ByJG\ApiTools\Exception\GenericSwaggerException;
use ByJG\ApiTools\Exception\HttpMethodNotFoundException;
use ByJG\ApiTools\Exception\InvalidDefinitionException;
use ByJG\ApiTools\Exception\InvalidRequestException;
use ByJG\ApiTools\Exception\NotMatchedException;
use ByJG\ApiTools\Exception\PathNotFoundException;
use ByJG\ApiTools\Exception\StatusCodeNotMatchedException;
use GuzzleHttp\Exception\RequestException;
use function PHPUnit\Framework\assertTrue;

trait AssertRequestAgainstSchema
{
    protected Schema $schema;

    /**
     * Configure the schema to use for requests
     *
     * When set, all requests without their own schema use this one instead.
     *
     * @param Schema $schema
     */
    public function setSchema($schema) : void
    {
        $this->schema = $schema;
    }

    /**
     * @param  AbstractRequester  $request
     * @return mixed
     * @throws GenericSwaggerException
     * @throws DefinitionNotFoundException
     * @throws HttpMethodNotFoundException
     * @throws InvalidDefinitionException
     * @throws InvalidRequestException
     * @throws NotMatchedException
     * @throws PathNotFoundException
     * @throws StatusCodeNotMatchedException
     * @throws RequestException
     */
    public function assertRequest(AbstractRequester $request)
    {
        // Add own schema if nothing is passed.
        if (!$request->hasSchema()) {
            $this->checkSchema();
            $request->withSchema($this->schema);
        }

        // Request based on the Swagger Request definitios
        $body = $request->send();

        // Note: This code is only reached if the send is successful and all matches are satisfied

        assertTrue(true);

        return $body;
    }

    /**
     * @throws GenericSwaggerException
     */
    protected function checkSchema(): void
    {
        if (!$this->schema) {
            throw new GenericSwaggerException('You have to configure a schema for either the request or the testcase');
        }
    }
}

