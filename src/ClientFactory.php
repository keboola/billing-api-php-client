<?php

declare(strict_types=1);

namespace Keboola\BillingApi;

class ClientFactory
{
    public function createClient(
        string $billingUrl,
        string $authToken,
        array $options = []
    ): Client {
        $internalClient = new InternalClient(
            $billingUrl,
            'X-StorageApi-Token',
            $authToken,
            $options
        );

        return new Client($internalClient);
    }

    public function createManageClient(
        string $billingUrl,
        string $authToken,
        array $options = []
    ): ManageClient {
        $internalClient = new InternalClient(
            $billingUrl,
            'X-KBC-ManageApiToken',
            $authToken,
            $options
        );

        return new ManageClient($internalClient);
    }
}
