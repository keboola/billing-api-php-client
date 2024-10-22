<?php

declare(strict_types=1);

namespace Keboola\BillingApi;

use GuzzleHttp\Psr7\Request;
use Keboola\BillingApi\Model\ConfirmSubscriptionParameters;
use Keboola\BillingApi\Model\ResolveTokenParameters;
use Keboola\BillingApi\Model\ResolveTokenResult;

class ManageClient
{
    private InternalClient $internalClient;

    public function __construct(InternalClient $internalClient)
    {
        $this->internalClient = $internalClient;
    }

    public function recordJobDuration(
        string $projectId,
        string $jobId,
        string $componentId,
        string $jobType,
        array $backend,
        float $durationSeconds,
    ): array {
        $request = new Request('PUT', 'duration/job', [], json_encode([
            'projectId' => $projectId,
            'jobId' => $jobId,
            'componentId' => $componentId,
            'jobType' => $jobType,
            'backend' => $backend,
            'durationSeconds' => $durationSeconds,
        ], JSON_THROW_ON_ERROR));
        return $this->internalClient->sendRequestWithResponse($request);
    }

    public function recordContainerSandboxDuration(
        string $projectId,
        string $sandboxId,
        string $sandboxType,
        string $sandboxSize,
        float $durationSeconds,
    ): void {
        $request = new Request('PUT', 'duration/container-sandbox', [], json_encode([
            'projectId' => $projectId,
            'sandboxId' => $sandboxId,
            'sandboxType' => $sandboxType,
            'sandboxSize' => $sandboxSize,
            'durationSeconds' => $durationSeconds,
        ], JSON_THROW_ON_ERROR));
        $this->internalClient->sendRequestWithResponse($request);
    }

    public function resolveMarketplaceToken(ResolveTokenParameters $parameters): ResolveTokenResult
    {
        $request = new Request('POST', 'marketplaces/resolve-token', [], json_encode([
            'vendor' => $parameters->vendor->value,
            'token' => $parameters->token,
        ], JSON_THROW_ON_ERROR));

        $response = $this->internalClient->sendRequestWithResponse($request);
        return ResolveTokenResult::fromResponse($response);
    }

    public function confirmMarketplaceSubscription(ConfirmSubscriptionParameters $parameters): void
    {
        $request = new Request('POST', 'marketplaces/confirm-subscription', [], json_encode([
            'subscriptionId' => $parameters->subscriptionId,
            'organizationId' => $parameters->organizationId,
            'projectId' => $parameters->projectId,
        ], JSON_THROW_ON_ERROR));

        $this->internalClient->sendRequestWithoutResponse($request);
    }
}
