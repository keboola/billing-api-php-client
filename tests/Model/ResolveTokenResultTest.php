<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Model;

use Keboola\BillingApi\Model\ResolveTokenResult;
use PHPUnit\Framework\TestCase;

class ResolveTokenResultTest extends TestCase
{
    public function testFromResponse(): void
    {
        $result = ResolveTokenResult::fromResponse([
            'id' => '123',
            'vendor' => 'aws',
            'vendorSubscriptionId' => '456',
            'productId' => '789',
            'planId' => 'plan',
            'state' => 'active',
            'organizationId' => 'org',
            'projectId' => 'proj',
            'dateCreated' => '2021-01-01T00:00:00+00:00',
            'dateModified' => '2022-01-01T00:00:00+00:00',
            'vendorData' => [
                'foo' => 'bar',
            ],
        ]);

        self::assertSame('123', $result->id);
        self::assertSame('aws', $result->vendor);
        self::assertSame('456', $result->vendorSubscriptionId);
        self::assertSame('789', $result->productId);
        self::assertSame('plan', $result->planId);
        self::assertSame('active', $result->state);
        self::assertSame('org', $result->organizationId);
        self::assertSame('proj', $result->projectId);
        self::assertSame('2021-01-01T00:00:00+00:00', $result->dateCreated->format(DATE_ATOM));
        self::assertSame('2022-01-01T00:00:00+00:00', $result->dateModified->format(DATE_ATOM));
        self::assertSame(['foo' => 'bar'], $result->vendorData);
    }
}
