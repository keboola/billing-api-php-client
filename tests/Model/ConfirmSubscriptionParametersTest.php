<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Model;

use InvalidArgumentException;
use Keboola\BillingApi\Model\ConfirmSubscriptionParameters;
use PHPUnit\Framework\TestCase;

class ConfirmSubscriptionParametersTest extends TestCase
{
    public function testSubscriptionIdMustNotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid subscription ID. The value must not be empty');

        new ConfirmSubscriptionParameters(
            '',
            'organization-id',
            'project-id',
        );
    }

    public function testOrganizationIdMustNotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid organization ID. The value must not be empty');

        new ConfirmSubscriptionParameters(
            'subscription-id',
            '',
            'project-id',
        );
    }

    public function testProjectIdMustNotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid project ID. The value must not be empty');

        new ConfirmSubscriptionParameters(
            'subscription-id',
            'organization-id',
            '',
        );
    }
}
