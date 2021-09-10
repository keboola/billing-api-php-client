<?php

namespace Keboola\BillingApi;

use Keboola\BillingApi\Exception\BillingException;
use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\Options\IndexOptions;

class CreditsChecker
{
    /** @var StorageApiClient */
    private $client;

    public function __construct(StorageApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * @return string|null
     */
    private function getBillingServiceUrl()
    {
        $options = new IndexOptions();
        $options->setExclude(['components']);

        $index = $this->client->indexAction($options);
        foreach ($index['services'] as $service) {
            if ($service['id'] === 'billing') {
                return (string) $service['url'];
            }
        }
        return null;
    }

    /**
     * @param string $token
     * @return Client
     */
    public function getBillingClient($token)
    {
        $url = $this->getBillingServiceUrl();
        if (!$url) {
            throw new BillingException(
                sprintf('Service "%s" was not found in KBC services', 'billing'),
                500
            );
        }

        return new Client($url, $token);
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
