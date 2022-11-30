<?php

declare(strict_types=1);

namespace Keboola\BillingApi\Model;

class ResolveTokenResult
{
    private string $subscriptionId;
    private ?string $projectId;

    public function __construct(
        string $subscriptionId,
        ?string $projectId
    ) {
        $this->subscriptionId = $subscriptionId;
        $this->projectId = $projectId;
    }

    public static function fromResponse(array $data): self
    {
        return new self(
            $data['subscriptionId'],
            $data['projectId'] ?? null,
        );
    }

    public function getSubscriptionId(): string
    {
        return $this->subscriptionId;
    }

    public function getProjectId(): ?string
    {
        return $this->projectId;
    }
}
