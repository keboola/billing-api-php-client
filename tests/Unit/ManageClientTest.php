<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Unit;

use DateTimeImmutable;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Keboola\BillingApi\InternalClient;
use Keboola\BillingApi\ManageClient;
use Keboola\BillingApi\Model\ConfirmSubscriptionParameters;
use Keboola\BillingApi\Model\MarketplaceVendor;
use Keboola\BillingApi\Model\ResolveTokenParameters;
use Keboola\BillingApi\Model\ResolveTokenResult;
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
            72.7,
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
            (string) $request->getBody(),
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

    public function testRecordContainerSandboxDuration(): void
    {
        $requestsMade = [];
        $responses = [
            new Response(200),
        ];

        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($requestsMade));
        $internalClient = new InternalClient('http://example.com', 'auth-header', 'dummy-token', [
            'handler' => $handlerStack,
        ]);

        $client = new ManageClient($internalClient);

        $client->recordContainerSandboxDuration(
            'project-id',
            'sandbox-id',
            'sandbox-type',
            'sandbox-size',
            14.1,
        );

        self::assertCount(1, $requestsMade);
        $request = $requestsMade[0]['request'];

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('http://example.com/duration/container-sandbox', (string) $request->getUri());
        self::assertSame('dummy-token', $request->getHeaderLine('auth-header'));
        self::assertSame(
            json_encode([
                'projectId' => 'project-id',
                'sandboxId' => 'sandbox-id',
                'sandboxType' => 'sandbox-type',
                'sandboxSize' => 'sandbox-size',
                'durationSeconds' => 14.1,
            ]),
            (string) $request->getBody(),
        );
    }

    /** @dataProvider provideResolveMarketplaceTokenTestData */
    public function testResolveMarketplaceToken(
        ResolveTokenParameters $parameters,
        array $expectedRequestData,
        array $responseData,
        ResolveTokenResult $expectedResult,
    ): void {
        $requestsMade = [];
        $responses = [
            new Response(200, [], (string) json_encode($responseData)),
        ];

        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($requestsMade));
        $internalClient = new InternalClient('http://example.com', 'auth-header', 'dummy-token', [
            'handler' => $handlerStack,
        ]);

        $client = new ManageClient($internalClient);

        $result = $client->resolveMarketplaceToken(new ResolveTokenParameters(
            MarketplaceVendor::AZURE,
            'token-value',
        ));

        self::assertCount(1, $requestsMade);
        $request = $requestsMade[0]['request'];

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://example.com/marketplaces/resolve-token', (string) $request->getUri());
        self::assertSame('dummy-token', $request->getHeaderLine('auth-header'));
        self::assertSame(json_encode($expectedRequestData), (string) $request->getBody());

        self::assertEquals($expectedResult, $result);
    }

    public function provideResolveMarketplaceTokenTestData(): iterable
    {
        yield 'new subscription without project' => [
            'parameters' => new ResolveTokenParameters(
                MarketplaceVendor::AZURE,
                'token-value',
            ),
            'expectedRequestData' => [
                'vendor' => 'azure',
                'token' => 'token-value',
            ],
            'responseData' => [
                'id' => 'subscription-id',
                'vendor' => 'aws',
                'vendorSubscriptionId' => '456',
                'productId' => '789',
                'planId' => 'plan',
                'state' => 'inactive',
                'organizationId' => null,
                'projectId' => null,
                'dateCreated' => '2021-01-01T00:00:00+00:00',
                'dateModified' => '2022-01-01T00:00:00+00:00',
                'vendorData' => [
                    'subscription' => [
                        'beneficiary' => [
                            'tenantId' => 'tenant-id',
                        ],
                    ],
                    'offerId' => 123,
                    'planId' => 123,
                ],
            ],
            'expectedResult' => new ResolveTokenResult(
                id: 'subscription-id',
                vendor: 'aws',
                vendorSubscriptionId: '456',
                productId: '789',
                planId: 'plan',
                state: 'inactive',
                organizationId: null,
                projectId: null,
                dateCreated: new DateTimeImmutable('2021-01-01T00:00:00+00:00'),
                dateModified: new DateTimeImmutable('2022-01-01T00:00:00+00:00'),
                vendorData: [
                    'subscription' => [
                        'beneficiary' => [
                            'tenantId' => 'tenant-id',
                        ],
                    ],
                    'offerId' => 123,
                    'planId' => 123,
                ],
            ),
        ];

        yield 'existing subscription with organization and project' => [
            'parameters' => new ResolveTokenParameters(
                MarketplaceVendor::AZURE,
                'token-value',
            ),
            'expectedRequestData' => [
                'vendor' => 'azure',
                'token' => 'token-value',
            ],
            'responseData' => [
                'id' => 'subscription-id',
                'vendor' => 'aws',
                'vendorSubscriptionId' => '456',
                'productId' => '789',
                'planId' => 'plan',
                'state' => 'inactive',
                'organizationId' => 'organization-id',
                'projectId' => 'project-id',
                'dateCreated' => '2021-01-01T00:00:00+00:00',
                'dateModified' => '2022-01-01T00:00:00+00:00',
                'vendorData' => [
                    'subscription' => [
                        'beneficiary' => [
                            'tenantId' => 'tenant-id',
                        ],
                    ],
                    'offerId' => 123,
                    'planId' => 123,
                ],
            ],
            'expectedResult' => new ResolveTokenResult(
                id: 'subscription-id',
                vendor: 'aws',
                vendorSubscriptionId: '456',
                productId: '789',
                planId: 'plan',
                state: 'inactive',
                organizationId: 'organization-id',
                projectId: 'project-id',
                dateCreated: new DateTimeImmutable('2021-01-01T00:00:00+00:00'),
                dateModified: new DateTimeImmutable('2022-01-01T00:00:00+00:00'),
                vendorData: [
                    'subscription' => [
                        'beneficiary' => [
                            'tenantId' => 'tenant-id',
                        ],
                    ],
                    'offerId' => 123,
                    'planId' => 123,
                ],
            ),
        ];
    }

    public function testConfirmMarketplaceSubscription(): void
    {
        $requestsMade = [];
        $responses = [
            new Response(200),
        ];

        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($requestsMade));
        $internalClient = new InternalClient('http://example.com', 'auth-header', 'dummy-token', [
            'handler' => $handlerStack,
        ]);

        $client = new ManageClient($internalClient);

        $client->confirmMarketplaceSubscription(new ConfirmSubscriptionParameters(
            'subscription-id',
            'organization-id',
            'project-id',
        ));

        self::assertCount(1, $requestsMade);
        $request = $requestsMade[0]['request'];

        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://example.com/marketplaces/confirm-subscription', (string) $request->getUri());
        self::assertSame('dummy-token', $request->getHeaderLine('auth-header'));
        self::assertSame(json_encode([
            'subscriptionId' => 'subscription-id',
            'organizationId' => 'organization-id',
            'projectId' => 'project-id',
        ]), (string) $request->getBody());
    }
}
