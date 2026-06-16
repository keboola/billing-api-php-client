<?php

declare(strict_types=1);

namespace Keboola\BillingApi\Model;

use Keboola\ApiClientBase\ResponseModelInterface;

/**
 * Passthrough response model that exposes the decoded JSON body as a raw array.
 *
 * The Billing API surfaces several endpoints that are consumed as plain arrays
 * rather than typed models; this lets {@see \Keboola\BillingApi\InternalClient}
 * keep returning arrays while delegating transport to the shared base client.
 */
final class ArrayResponse implements ResponseModelInterface
{
    public function __construct(
        public readonly array $data,
    ) {
    }

    public static function fromResponseData(array $data): static
    {
        return new self($data);
    }
}
