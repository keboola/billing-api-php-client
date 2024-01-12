<?php

declare(strict_types=1);

namespace Keboola\BillingApi\Model;

use DateTimeImmutable;

class ResolveTokenResult
{
    public const VENDOR_AWS = 'aws';
    public const VENDOR_AZURE = 'azure';
    public const VENDOR_GCP = 'gcp';

    public const STATE_INACTIVE = 'inactive';
    public const STATE_ACTIVE = 'active';
    public const STATE_SUSPENDED = 'suspended';
    public const STATE_UNSUBSCRIBED = 'unsubscribed';

    public function __construct(
        public readonly string $id,
        public readonly string $vendor,
        public readonly string $vendorSubscriptionId,
        public readonly ?string $productId,
        public readonly string $planId,
        public readonly string $state,
        public readonly ?string $organizationId,
        public readonly ?string $projectId,
        public readonly DateTimeImmutable $dateCreated,
        public readonly DateTimeImmutable $dateModified,
        public readonly array $vendorData,
    ) {
    }

    public static function fromResponse(array $data): self
    {
        return new self(
            $data['id'],
            $data['vendor'],
            $data['vendorSubscriptionId'],
            $data['productId'] ?? null,
            $data['planId'],
            $data['state'],
            $data['organizationId'] ?? null,
            $data['projectId'] ?? null,
            new DateTimeImmutable($data['dateCreated']),
            new DateTimeImmutable($data['dateModified']),
            $data['vendorData'],
        );
    }
}
