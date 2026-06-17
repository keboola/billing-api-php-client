<?php

declare(strict_types=1);

namespace Keboola\BillingApi\Exception;

use Keboola\ApiClientBase\Exception\ClientException;

/**
 * Thrown for every Billing client failure.
 *
 * Extends the base client's {@see ClientException} so it can be supplied to
 * {@see \Keboola\ApiClientBase\ApiClient} as its exception class — the base
 * client then throws it directly on transport/HTTP/decoding errors, carrying
 * the HTTP status code and raw response body (see getStatusCode()/getResponseBody()).
 */
class BillingException extends ClientException
{
}
