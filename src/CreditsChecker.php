<?php

declare(strict_types=1);

namespace Keboola\BillingApi;

use Keboola\BillingApi\Exception\BillingException;
use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\Options\IndexOptions;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type Options from InternalClient as ClientOptions
 */
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

    /**
     * @param non-empty-string $storageToken
     * @param ClientOptions $options
     */
    public function getBillingClient(string $storageToken, array $options = []): Client
    {
        $url = $this->getBillingServiceUrl();
        if (!$url) {
            throw new BillingException(
                sprintf('Service "%s" was not found in KBC services', 'billing'),
                500,
            );
        }

        return $this->clientFactory->createClient($url, $storageToken, $options);
    }

    /**
     * @param ClientOptions $clientOptions
     */
    public function hasCredits(bool $tryTopUp = false, array $clientOptions = []): bool
    {
        $url = $this->getBillingServiceUrl();
        if (!$url) {
            return true; // billing service not available, run everything
        }
        $tokenInfo = $this->client->verifyToken();
        if (!in_array('pay-as-you-go', $tokenInfo['owner']['features'])) {
            return true; // not a payg project, run everything
        }

        $storageToken = $this->client->getTokenString();
        Assert::stringNotEmpty($storageToken, 'Storage API token must not be empty');
        $billingClient = $this->getBillingClient($storageToken, $clientOptions);

        if ($tryTopUp) {
            $remaining = $billingClient->getRemainingCreditsWithOptionalTopUp();
        } else {
            $remaining = $billingClient->getRemainingCredits();
        }
        return $remaining > 0;
    }
}
