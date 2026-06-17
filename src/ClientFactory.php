<?php

declare(strict_types=1);

namespace Keboola\BillingApi;

use Keboola\ApiClientBase\Auth\KeboolaServiceAccountAuthenticator;
use Keboola\ApiClientBase\Auth\ManageApiTokenAuthenticator;
use Keboola\ApiClientBase\Auth\StorageApiTokenAuthenticator;
use Keboola\BillingApi\Exception\BillingException;

/**
 * @phpstan-import-type Options from InternalClient
 */
class ClientFactory
{
    /**
     * @param Options $options
     * @return Client
     */
    public function createClient(
        string $billingUrl,
        string $authToken,
        array $options = [],
    ): Client {
        $internalClient = new InternalClient(
            $billingUrl,
            new StorageApiTokenAuthenticator($this->requireToken($authToken)),
            $options,
        );

        return new Client($internalClient);
    }

    /**
     * @param Options $options
     * @return ManageClient
     */
    public function createManageClient(
        string $billingUrl,
        ?string $authToken = null,
        array $options = [],
    ): ManageClient {
        // A provided token authenticates as a Manage API token; with no token we fall back to the
        // projected Kubernetes service-account token (read from disk by the base authenticator).
        $authenticator = ($authToken === null || $authToken === '')
            ? new KeboolaServiceAccountAuthenticator()
            : new ManageApiTokenAuthenticator($authToken);

        $internalClient = new InternalClient($billingUrl, $authenticator, $options);

        return new ManageClient($internalClient);
    }

    /**
     * @return non-empty-string
     */
    private function requireToken(string $authToken): string
    {
        if ($authToken === '') {
            throw new BillingException('Invalid parameters when creating client: token must not be empty');
        }

        return $authToken;
    }
}
