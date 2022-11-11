<?php

declare(strict_types=1);

namespace Keboola\BillingApi;

use GuzzleHttp\Psr7\Request;

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
        float $durationSeconds
    ): array {
        $request = new Request('PUT', 'duration/job', [], json_encode([
            'projectId' => $projectId,
            'jobId' => $jobId,
            'componentId' => $componentId,
            'jobType' => $jobType,
            'backend' => $backend,
            'durationSeconds' => $durationSeconds,
        ], JSON_THROW_ON_ERROR));
        return $this->internalClient->sendRequest($request);
    }
}
