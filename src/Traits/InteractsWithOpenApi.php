<?php
/** @noinspection PhpParamsInspection */

namespace RouxtAccess\OpenApi\Testing\Laravel\Traits;

use ByJG\ApiTools\Base\Schema;
use ByJG\Util\Psr7\MessageException;
use Illuminate\Http\Response;
use RouxtAccess\OpenApi\Testing\Laravel\LaravelRequester;


trait InteractsWithOpenApi
{
    use AssertRequestAgainstSchema;

    protected LaravelRequester $requester;
    protected Response $response;
    protected array $responseBody;
    protected array $requestHeader;
    protected static Schema $cachedSchema;


    /**
     * @throws MessageException
     */
    protected function setUpOpenApiTester(): void
    {
        $this->loadSchema();
        $this->requester = new LaravelRequester($this);
    }

    protected function loadSchema(): void
    {
        // Load only once, must be made in setup to be able to use base_path
        if (isset($this->schema)) {
            return;
        }

        // Load only once per phpunit instance
        if (!isset(self::$cachedSchema)) {
            self::$cachedSchema = Schema::getInstance($this->getSchemaContents());
        }

        // Set the schema
        $this->setSchema(self::$cachedSchema);
    }

    /**
     * @return false|string
     */
    protected function getSchemaContents()
    {
        return file_get_contents(storage_path('api-docs/api-docs.json'));
    }

    protected function checkRequesterIsInstantiated(): void
    {
        if(!isset($this->requester))
        {
            throw new \RuntimeException('Requester is not instantiated. Have you incorrectly overridden the setUp method?');
        }
    }

}
