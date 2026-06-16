<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Keboola\ApiClientBase\Auth\StorageApiTokenAuthenticator;
use Keboola\BillingApi\Exception\BillingException;
use Keboola\BillingApi\InternalClient;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class InternalClientTest extends TestCase
{
    private function getClient(array $options): InternalClient
    {
        return new InternalClient(
            'https://example.com/',
            new StorageApiTokenAuthenticator('authToken'),
            $options,
        );
    }

    public function testCreateClientInvalidBackoff(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "abc" is invalid: This value should be a valid number',
        );
        new InternalClient(
            'https://example.com/',
            new StorageApiTokenAuthenticator('authToken'),
            // @phpstan-ignore-next-line we test passing invalid value
            ['backoffMaxTries' => 'abc'],
        );
    }

    public function testCreateClientTooLowBackoff(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "-1" is invalid: This value should be between 0 and 100.',
        );
        new InternalClient(
            'https://example.com/',
            new StorageApiTokenAuthenticator('authToken'),
            // @phpstan-ignore-next-line we test passing invalid value
            ['backoffMaxTries' => -1],
        );
    }

    public function testCreateClientTooHighBackoff(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "101" is invalid: This value should be between 0 and 100.',
        );
        new InternalClient(
            'https://example.com/',
            new StorageApiTokenAuthenticator('authToken'),
            // @phpstan-ignore-next-line we test passing invalid value
            ['backoffMaxTries' => 101],
        );
    }

    public function testCreateClientInvalidUrl(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "invalid url" is invalid: This value is not a valid URL.',
        );
        new InternalClient('invalid url', new StorageApiTokenAuthenticator('authToken'));
    }

    public function testClientRequestResponse(): void
    {
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                '{
                    "remaining": "123.4343434343434343",
                    "consumed": "456.1212121212121212"
                }',
            ),
        ]);

        $client = $this->getClient(['handler' => HandlerStack::create($mock)]);
        $response = $client->sendRequestWithResponse(new Request('GET', 'credits'));

        self::assertSame([
            'remaining' => '123.4343434343434343',
            'consumed' => '456.1212121212121212',
        ], $response);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertEquals('https://example.com/credits', $request->getUri()->__toString());
        self::assertEquals('GET', $request->getMethod());
        self::assertEquals('authToken', $request->getHeaderLine('X-StorageApi-Token'));
        self::assertEquals('Billing PHP Client', $request->getHeaderLine('User-Agent'));
        self::assertEquals('application/json', $request->getHeaderLine('Content-type'));
    }

    public function testClientRequestEmptyResponseThrows(): void
    {
        // The shared base client decodes every response body as JSON. An empty body is no
        // longer silently coerced to []; it surfaces as a BillingException. Endpoints that
        // legitimately return no body are sent via sendRequestWithoutResponse().
        $mock = new MockHandler([
            new Response(200),
        ]);

        $client = $this->getClient(['handler' => HandlerStack::create($mock)]);

        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('Response is not valid JSON');
        $client->sendRequestWithResponse(new Request('GET', 'credits'));
    }

    public function testInvalidResponse(): void
    {
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                'invalid json',
            ),
        ]);

        $client = $this->getClient(['handler' => HandlerStack::create($mock)]);

        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('Response is not valid JSON: Syntax error');
        $client->sendRequestWithResponse(new Request('GET', 'credits'));
    }

    public function testLogger(): void
    {
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                '{
                    "remaining": "123",
                    "consumed": "456"
                }',
            ),
        ]);

        $logsHandler = new TestHandler();
        $logger = new Logger('test', [$logsHandler]);

        $client = $this->getClient([
            'handler' => HandlerStack::create($mock),
            'logger' => $logger,
            'userAgent' => 'test agent',
        ]);
        $client->sendRequestWithResponse(new Request('GET', 'credits'));

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertEquals('test agent', $request->getHeaderLine('User-Agent'));
        self::assertTrue($logsHandler->hasInfoThatContains('GET https://example.com/credits : 200'));
    }

    public function testRetrySuccess(): void
    {
        $mock = new MockHandler([
            new Response(500, ['Content-Type' => 'application/json'], 'Out of order'),
            new Response(500, ['Content-Type' => 'application/json'], 'Out of order'),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                '{
                    "remaining": "123",
                    "consumed": "456"
                }',
            ),
        ]);

        $client = $this->getClient(['handler' => HandlerStack::create($mock)]);
        $response = $client->sendRequestWithResponse(new Request('GET', 'credits'));

        self::assertSame([
            'remaining' => '123',
            'consumed' => '456',
        ], $response);

        // all three queued responses consumed => two retries happened
        self::assertSame(0, $mock->count());
        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertEquals('https://example.com/credits', $request->getUri()->__toString());
    }

    public function testRetryFailure(): void
    {
        $responses = [];
        for ($i = 0; $i < 30; $i++) {
            $responses[] = new Response(500, ['Content-Type' => 'application/json'], 'Out of order');
        }
        $mock = new MockHandler($responses);
        $client = $this->getClient(['handler' => HandlerStack::create($mock), 'backoffMaxTries' => 1]);
        try {
            $client->sendRequestWithResponse(new Request('GET', 'credits'));
            self::fail('Must throw exception');
        } catch (BillingException $e) {
            self::assertStringContainsString('500 Internal Server Error', $e->getMessage());
        }
        // initial attempt + one retry => 2 of 30 responses consumed
        self::assertSame(28, $mock->count());
    }

    public function testRetryFailureReducedBackoff(): void
    {
        $responses = [];
        for ($i = 0; $i < 30; $i++) {
            $responses[] = new Response(500, ['Content-Type' => 'application/json'], 'Out of order');
        }
        $mock = new MockHandler($responses);
        $client = $this->getClient(['handler' => HandlerStack::create($mock), 'backoffMaxTries' => 3]);
        try {
            $client->sendRequestWithResponse(new Request('GET', 'credits'));
            self::fail('Must throw exception');
        } catch (BillingException $e) {
            self::assertStringContainsString('500 Internal Server Error', $e->getMessage());
        }
        // initial attempt + three retries => 4 of 30 responses consumed
        self::assertSame(26, $mock->count());
    }

    public static function provideClientTimeoutOptions(): iterable
    {
        yield 'defaults' => [
            'options' => [],
            'expectedTimeout' => 120,
            'expectedConnectTimeout' => 10,
        ];

        yield 'custom timeouts' => [
            'options' => [
                'timeout' => 100,
                'connectTimeout' => 50,
            ],
            'expectedTimeout' => 100,
            'expectedConnectTimeout' => 50,
        ];
    }

    /**
     * @dataProvider provideClientTimeoutOptions
     */
    public function testTimeoutConfiguration(
        array $options,
        int $expectedTimeout,
        int $expectedConnectTimeout,
    ): void {
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                '{
                    "remaining": "123.4343434343434343",
                    "consumed": "456.1212121212121212"
                }',
            ),
        ]);

        $client = $this->getClient([
            'handler' => HandlerStack::create($mock),
            ...$options,
        ]);
        $response = $client->sendRequestWithResponse(new Request('GET', 'credits'));

        self::assertSame([
            'remaining' => '123.4343434343434343',
            'consumed' => '456.1212121212121212',
        ], $response);

        $lastOptions = $mock->getLastOptions();
        self::assertSame($expectedTimeout, $lastOptions['timeout'] ?? null);
        self::assertSame($expectedConnectTimeout, $lastOptions['connect_timeout'] ?? null);
    }
}
