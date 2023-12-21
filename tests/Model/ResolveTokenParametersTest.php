<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Model;

use InvalidArgumentException;
use Keboola\BillingApi\Model\MarketplaceVendor;
use Keboola\BillingApi\Model\ResolveTokenParameters;
use PHPUnit\Framework\TestCase;

class ResolveTokenParametersTest extends TestCase
{
    public function testTokenMustNotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token. The value must not be empty');

        new ResolveTokenParameters(
            MarketplaceVendor::AZURE,
            '',
        );
    }
}
