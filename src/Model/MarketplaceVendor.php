<?php

declare(strict_types=1);

namespace Keboola\BillingApi\Model;

enum MarketplaceVendor: string
{
    case AZURE = 'azure';
    case GCP = 'gcp';
}
