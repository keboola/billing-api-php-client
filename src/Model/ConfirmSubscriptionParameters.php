<?php

declare(strict_types=1);

namespace Keboola\BillingApi\Model;

use InvalidArgumentException;

class ConfirmSubscriptionParameters
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $organizationId,
        public readonly string $projectId,
    ) {
        if ($this->subscriptionId === '') {
            throw new InvalidArgumentException('Invalid subscription ID. The value must not be empty');
        }

        if ($this->organizationId === '') {
            throw new InvalidArgumentException('Invalid organization ID. The value must not be empty');
        }

        if ($this->projectId === '') {
            throw new InvalidArgumentException('Invalid project ID. The value must not be empty');
        }
    }
}
