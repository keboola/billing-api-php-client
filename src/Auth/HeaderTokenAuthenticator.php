<?php

declare(strict_types=1);

namespace Keboola\BillingApi\Auth;

use Keboola\ApiClientBase\Auth\RequestAuthenticatorInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Authenticates a request by setting a single token header. The header name is
 * configurable so the same client can authenticate with a Storage API token
 * (`X-StorageApi-Token`) or a Manage API token (`X-KBC-ManageApiToken`).
 */
final class HeaderTokenAuthenticator implements RequestAuthenticatorInterface
{
    public function __construct(
        private readonly string $headerName,
        private readonly string $token,
    ) {
    }

    public function __invoke(RequestInterface $request): RequestInterface
    {
        return $request->withHeader($this->headerName, $this->token);
    }
}
