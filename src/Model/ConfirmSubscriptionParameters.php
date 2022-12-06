<?php

declare(strict_types=1);

namespace Keboola\BillingApi\Model;

use InvalidArgumentException;

class ConfirmSubscriptionParameters
{
    private string $subscriptionId;
    private string $projectId;

    public function __construct(
        string $subscriptionId,
        string $projectId
    ) {
        if ($subscriptionId === '') {
            throw new InvalidArgumentException('Invalid subscription ID. The value must not be empty');
        }

        if ($projectId === '') {
            throw new InvalidArgumentException('Invalid project ID. The value must not be empty');
        }

        $this->subscriptionId = $subscriptionId;
        $this->projectId = $projectId;
    }

    public function getSubscriptionId(): string
    {
        return $this->subscriptionId;
    }

    public function getProjectId(): string
    {
        return $this->projectId;
    }
}
