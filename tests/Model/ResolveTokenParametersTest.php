<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Model;

use InvalidArgumentException;
use Keboola\BillingApi\Model\ResolveTokenParameters;
use PHPUnit\Framework\TestCase;

class ResolveTokenParametersTest extends TestCase
{
    public function testCreate(): void
    {
        $parameters = new ResolveTokenParameters(
            'azure',
            'token',
        );

        self::assertSame('azure', $parameters->getVendor());
        self::assertSame('token', $parameters->getToken());
    }

    public function testVendorMustBeValid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid vendor "foo". Possible values are: "azure"');

        new ResolveTokenParameters(
            'foo',
            'token',
        );
    }

    public function testTokenMustNotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token. The value must not be empty');

        new ResolveTokenParameters(
            'azure',
            '',
        );
    }
}
