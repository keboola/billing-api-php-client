<?php

declare(strict_types=1);

namespace Keboola\BillingApi\Model;

use InvalidArgumentException;

class ResolveTokenParameters
{
    public function __construct(
        public readonly MarketplaceVendor $vendor,
        public readonly string $token,
    ) {
        if ($this->token === '') {
            throw new InvalidArgumentException('Invalid token. The value must not be empty');
        }
    }
}
