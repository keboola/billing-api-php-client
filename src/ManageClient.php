<?php

declare(strict_types=1);

namespace Keboola\BillingApi;

use Closure;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use Keboola\BillingApi\Exception\BillingException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Throwable;

class ManageClient
{
    private InternalClient $internalClient;

    public function __construct(InternalClient $internalClient)
    {
        $this->internalClient = $internalClient;
    }

    public function recordJobDuration(string $projectId, string $jobId, float $durationSeconds): array
    {
        $request = new Request('PUT', 'duration/job', [], json_encode([
            'projectId' => $projectId,
            'jobId' => $jobId,
            'durationSeconds' => $durationSeconds,
        ], JSON_THROW_ON_ERROR));
        return $this->internalClient->sendRequest($request);
    }
}
