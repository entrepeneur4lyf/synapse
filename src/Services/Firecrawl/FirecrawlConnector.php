<?php

declare(strict_types=1);

namespace UseTheFork\Synapse\Services\Firecrawl;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Saloon\Traits\Plugins\HasTimeout;

class FirecrawlConnector extends Connector
{
    use AcceptsJson, AlwaysThrowOnErrors, HasTimeout;

    protected int $connectTimeout = 60;

    protected int $requestTimeout = 120;

    public function __construct(
        public readonly string $apiKey
    ) {
        //
    }

    /**
     * The Base URL of the API
     */
    public function resolveBaseUrl(): string
    {
        return 'https://api.firecrawl.dev';
    }

    /**
     * Default headers for every request
     */
    protected function defaultHeaders(): array
    {
      return [
        'Authorization' => 'Bearer '.$this->apiKey,
      ];
    }
}