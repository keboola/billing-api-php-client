<?php

declare(strict_types=1);

namespace Keboola\BillingApi\Model;

use InvalidArgumentException;

class ResolveTokenParameters
{
    public const VENDOR_AZURE = 'azure';

    private const VALID_VENDORS = [
        self::VENDOR_AZURE,
    ];

    private string $vendor;
    private string $token;

    public function __construct(
        string $vendor,
        string $token
    ) {
        if (!in_array($vendor, self::VALID_VENDORS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid vendor "%s". Possible values are: %s',
                $vendor,
                implode(', ', array_map(fn(string $v) => sprintf('"%s"', $v), self::VALID_VENDORS))
            ));
        }

        if ($token === '') {
            throw new InvalidArgumentException('Invalid token. The value must not be empty');
        }

        $this->vendor = $vendor;
        $this->token = $token;
    }

    public function getVendor(): string
    {
        return $this->vendor;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
