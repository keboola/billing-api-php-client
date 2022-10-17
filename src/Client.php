<?php

declare(strict_types=1);

namespace Keboola\BillingApi;

use GuzzleHttp\Psr7\Request;

class Client
{
    private InternalClient $internalClient;

    public function __construct(InternalClient $internalClient)
    {
        $this->internalClient = $internalClient;
    }

    public function getRemainingCredits(): float
    {
        $request = new Request('GET', 'credits', []);
        $data = $this->internalClient->sendRequest($request);
        return (double) $data['remaining'];
    }
}
