<?php

declare(strict_types=1);

namespace Keboola\BillingApi;

use Keboola\BillingApi\Exception\BillingException;
use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\Options\IndexOptions;

class CreditsChecker
{
    private ClientFactory $clientFactory;
    private StorageApiClient $client;

    public function __construct(ClientFactory $clientFactory, StorageApiClient $client)
    {
        $this->clientFactory = $clientFactory;
        $this->client = $client;
    }

    private function getBillingServiceUrl(): ?string
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

    public function getBillingClient(string $token): Client
    {
        $url = $this->getBillingServiceUrl();
        if (!$url) {
            throw new BillingException(
                sprintf('Service "%s" was not found in KBC services', 'billing'),
                500,
            );
        }

        return $this->clientFactory->createClient($url, $token);
    }

    public function hasCredits(bool $tryTopUp = false): bool
    {
        $url = $this->getBillingServiceUrl();
        if (!$url) {
            return true; // billing service not available, run everything
        }
        $tokenInfo = $this->client->verifyToken();
        if (!in_array('pay-as-you-go', $tokenInfo['owner']['features'])) {
            return true; // not a payg project, run everything
        }
        if ($tryTopUp) {
            $remaining = $this->getBillingClient($this->client->getTokenString())
                ->getRemainingCreditsWithOptionalTopUp();
        } else {
            $remaining = $this->getBillingClient($this->client->getTokenString())->getRemainingCredits();
        }
        return $remaining > 0;
    }
}
