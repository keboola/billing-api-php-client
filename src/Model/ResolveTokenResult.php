<?php

declare(strict_types=1);

namespace Keboola\BillingApi\Model;

class ResolveTokenResult
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly array $subscriptionData,
        public readonly ?string $organizationId,
        public readonly ?string $projectId,
    ) {
    }

    public static function fromResponse(array $data): self
    {
        return new self(
            $data['subscriptionId'],
            $data['subscriptionData'],
            $data['organizationId'] ?? null,
            $data['projectId'] ?? null,
        );
    }
}
