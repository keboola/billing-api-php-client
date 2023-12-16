<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Model;

use InvalidArgumentException;
use Keboola\BillingApi\Model\ConfirmSubscriptionParameters;
use PHPUnit\Framework\TestCase;

class ConfirmSubscriptionParametersTest extends TestCase
{
    public function testCreate(): void
    {
        $parameters = new ConfirmSubscriptionParameters(
            'subscription-id',
            'project-id',
        );

        self::assertSame('subscription-id', $parameters->getSubscriptionId());
        self::assertSame('project-id', $parameters->getProjectId());
    }

    public function testSubscriptionIdMustNotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid subscription ID. The value must not be empty');

        new ConfirmSubscriptionParameters(
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
            '',
        );
    }
}
