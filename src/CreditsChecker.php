<?php

namespace Keboola\BillingApi;

use Keboola\BillingApi\Exception\BillingClientException;
use Keboola\StorageApi\Client;
use Psr\Log\NullLogger;

class CreditsChecker
{
    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @return string|null
     */
    private function getBillingServiceUrl()
    {
        $index = $this->client->indexAction();
        foreach ($index['services'] as $service) {
            if ($service['id'] === 'billing') {
                return (string) $service['url'];
            }
        }
        return null;
    }

    /**
     * @param string $token
     * @return BillingClient
     */
    public function getBillingClient($token)
    {
        $url = $this->getBillingServiceUrl();
        if (!$url) {
            throw new BillingClientException(
                sprintf('Service "%s" was not found in KBC services', 'billing'),
                500
            );
        }

        return new BillingClient($url, $token);
    }

    /**
     * @return bool
     */
    public function hasCredits()
    {
        $url = $this->getBillingServiceUrl();
        if (!$url) {
            return true; // billing service not available, run everything
        }
        $tokenInfo = $this->client->verifyToken();
        if (!in_array('pay-as-you-go', $tokenInfo['owner']['features'])) {
            return true; // not a payg project, run everything
        }
        return $this->getBillingClient($this->client->token)->getRemainingCredits() > 0;
    }
}
