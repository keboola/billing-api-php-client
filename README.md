# Billing API PHP Client [![Build Status](https://dev.azure.com/keboola-dev/billing-api-php-client/_apis/build/status/keboola.billing-api-php-client?branchName=main)](https://dev.azure.com/keboola-dev/billing-api-php-client/_build/latest?definitionId=89&branchName=main)

PHP client for the Billing API ([API docs](https://keboolabillingapi.docs.apiary.io/#)).

## Usage
```bash
composer require keboola/billing-api-php-client
```

```php
use Keboola\BillingApi\Client;

$client = new Client(
    'http://billing.keboola.com/',
    'xxx-xxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
);
$credits = $client->getRemainingCredits();
var_dump($credits);
```

## Run tests
- With the above setup, you can run tests:

    ```bash
    docker-compose build
    docker-compose run tests
    ```

- To run tests with local code use:

    ```bash
    docker-compose run tests-local composer install
    docker-compose run tests-local
    ```
