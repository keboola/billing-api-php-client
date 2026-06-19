<?php

declare(strict_types=1);

namespace Keboola\BillingApi;

use Keboola\ApiClientBase\Auth\KeboolaServiceAccountAuthenticator;
use Keboola\ApiClientBase\Auth\ManageApiTokenAuthenticator;
use Keboola\ApiClientBase\Auth\StorageApiTokenAuthenticator;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type Options from InternalClient
 */
class ClientFactory
{
    /**
     * @param non-empty-string $storageToken
     * @param Options $options
     * @return Client
     */
    public function createClient(
        string $billingUrl,
        string $storageToken,
        array $options = [],
    ): Client {
        Assert::stringNotEmpty($storageToken, 'Storage API token must not be empty');

        $internalClient = new InternalClient(
            $billingUrl,
            new StorageApiTokenAuthenticator($storageToken),
            $options,
        );

        return new Client($internalClient);
    }

    /**
     * @param non-empty-string|null $manageToken
     * @param Options $options
     * @return ManageClient
     */
    public function createManageClient(
        string $billingUrl,
        ?string $manageToken = null,
        array $options = [],
    ): ManageClient {
        // A provided token authenticates as a Manage API token; with no token we fall back to the
        // projected Kubernetes service-account token (read from disk by the base authenticator).
        if ($manageToken === null) {
            $authenticator = new KeboolaServiceAccountAuthenticator();
        } else {
            Assert::stringNotEmpty($manageToken, 'Manage API token must not be empty');
            $authenticator = new ManageApiTokenAuthenticator($manageToken);
        }

        $internalClient = new InternalClient($billingUrl, $authenticator, $options);

        return new ManageClient($internalClient);
    }
}
