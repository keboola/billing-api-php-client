# Billing API PHP Client [![Build Status](https://dev.azure.com/keboola-dev/billing-api-php-client/_apis/build/status/keboola.billing-api-php-client?branchName=main)](https://dev.azure.com/keboola-dev/billing-api-php-client/_build/latest?definitionId=89&branchName=main)

PHP client for the Billing API ([API docs](https://keboolabillingapi.docs.apiary.io/#)).

Built on [`keboola/php-api-client-base`](https://github.com/keboola/php-api-client-base),
the shared base for Keboola service API clients (HTTP transport, authentication,
retries, JSON handling and error normalization).

## Usage
```bash
composer require keboola/billing-api-php-client
```

Clients are created through `ClientFactory`, which wires the correct authentication
header for each client (`X-StorageApi-Token` for the project client,
`X-KBC-ManageApiToken` for the manage client):

```php
use Keboola\BillingApi\ClientFactory;

$factory = new ClientFactory();

// Project client (authenticated with a Storage API token).
$client = $factory->createClient('https://billing.keboola.com/', $storageApiToken);
$credits = $client->getRemainingCredits();
var_dump($credits);

// Manage client (authenticated with a Manage API token).
$manageClient = $factory->createManageClient('https://billing.keboola.com/', $manageApiToken);

// Manage client without a token: authenticates with the projected Kubernetes
// service-account token (X-Kubernetes-Authorization), read from the standard
// mount path at request time. Use this when running inside the cluster.
$manageClient = $factory->createManageClient('https://billing.keboola.com/');
```

`createManageClient()`'s token is optional: pass a Manage API token to authenticate
with it, or omit it (leave it `null`) to fall back to the projected service-account
token. `createClient()` always requires a Storage API token. A non-null token must
be a non-empty string — an empty token is rejected (`Webmozart\Assert\InvalidArgumentException`).

`createClient()` and `createManageClient()` accept an optional `$options` array
(`backoffMaxTries`, `timeout`, `connectTimeout`, `userAgent`, `logger`).

For a custom authentication scheme, construct `InternalClient` directly with any
`Keboola\ApiClientBase\Auth\RequestAuthenticatorInterface` implementation:

```php
use Keboola\ApiClientBase\Auth\ManageApiTokenAuthenticator;
use Keboola\BillingApi\InternalClient;
use Keboola\BillingApi\ManageClient;

$manageClient = new ManageClient(
    new InternalClient('https://billing.keboola.com/', new ManageApiTokenAuthenticator($manageApiToken)),
);
```

## Error handling
Every failure (transport error, non-2xx response, invalid/empty body) throws a
`Keboola\BillingApi\Exception\BillingException`. It extends the base client's
`Keboola\ApiClientBase\Exception\ClientException`, so it also exposes the HTTP
status code and the raw response body when a response was received:

```php
use Keboola\BillingApi\Exception\BillingException;

try {
    $credits = $client->getRemainingCredits();
} catch (BillingException $e) {
    $e->getStatusCode();   // ?int  — HTTP status, or null for transport/connection failures
    $e->getResponseBody(); // ?string — raw response body when available
}
```

## Run tests
- With the above setup, you can run tests:

    ```bash
    docker compose build
    docker compose run tests
    ```

- To run tests with local code use:

    ```bash
    docker compose run tests-local composer install
    docker compose run tests-local
    ```

## License

MIT licensed, see [LICENSE](./LICENSE) file.
