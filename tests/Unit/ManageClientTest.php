<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Keboola\BillingApi\InternalClient;
use Keboola\BillingApi\ManageClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class ManageClientTest extends TestCase
{
    public function testRecordJobDuration(): void
    {
        $requestsMade = [];
        $responses = [
            new Response(200, [], (string) json_encode([
                'projectId' => 'project-id',
                'jobId' => 'job-id',
                'componentId' => 'keboola.component',
                'jobType' => 'standard',
                'backend' => [
                    'type' => 'small',
                ],
                'durationSeconds' => 72.7,
            ])),
        ];

        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($requestsMade));
        $internalClient = new InternalClient('http://example.com', 'auth-header', 'dummy-token', [
            'handler' => $handlerStack,
        ]);

        $client = new ManageClient($internalClient);

        $result = $client->recordJobDuration(
            'project-id',
            'job-id',
            'keboola.component',
            'standard',
            ['type' => 'small'],
            72.7
        );

        self::assertCount(1, $requestsMade);
        $request = $requestsMade[0]['request'];

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('http://example.com/duration/job', (string) $request->getUri());
        self::assertSame('dummy-token', $request->getHeaderLine('auth-header'));
        self::assertSame(
            json_encode([
                'projectId' => 'project-id',
                'jobId' => 'job-id',
                'componentId' => 'keboola.component',
                'jobType' => 'standard',
                'backend' => [
                    'type' => 'small',
                ],
                'durationSeconds' => 72.7,
            ]),
            (string) $request->getBody()
        );

        self::assertSame([
            'projectId' => 'project-id',
            'jobId' => 'job-id',
            'componentId' => 'keboola.component',
            'jobType' => 'standard',
            'backend' => [
                'type' => 'small',
            ],
            'durationSeconds' => 72.7,
        ], $result);
    }
}
