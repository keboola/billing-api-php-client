<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Keboola\BillingApi\Client;
use Keboola\BillingApi\Exception\BillingException;
use Keboola\BillingApi\InternalClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\Test\TestLogger;

class InternalClientTest extends TestCase
{
    private function getClient(array $options): InternalClient
    {
        return new InternalClient(
            'https://example.com/',
            'authHeader',
            'authToken',
            $options
        );
    }

    public function testCreateClientInvalidBackoff(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "abc" is invalid: This value should be a valid number'
        );
        new InternalClient(
            'https://example.com/',
            'authHeader',
            'authToken',
            // @phpstan-ignore-next-line we test passing invalid value
            ['backoffMaxTries' => 'abc']
        );
    }

    public function testCreateClientTooLowBackoff(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "-1" is invalid: This value should be between 0 and 100.'
        );
        new InternalClient(
            'https://example.com/',
            'authHeader',
            'authToken',
            // @phpstan-ignore-next-line we test passing invalid value
            ['backoffMaxTries' => -1]
        );
    }

    public function testCreateClientTooHighBackoff(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "101" is invalid: This value should be between 0 and 100.'
        );
        new InternalClient(
            'https://example.com/',
            'authHeader',
            'authToken',
            // @phpstan-ignore-next-line we test passing invalid value
            ['backoffMaxTries' => 101]
        );
    }

    public function testCreateClientInvalidUrl(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "invalid url" is invalid: This value is not a valid URL.'
        );
        new InternalClient('invalid url', 'authHeader', 'authToken');
    }

    public function testCreateClientInvalidAuthHeader(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "" is invalid: This value should not be blank.'
        );
        new InternalClient('https://example.com/', '', 'authToken');
    }

    public function testCreateClientInvalidAuthToken(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "" is invalid: This value should not be blank.'
        );
        new InternalClient('https://example.com/', 'authHeader', '');
    }

    public function testCreateClientMultipleErrors(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "invalid url" is invalid: This value is not a valid URL.'
        );
        new InternalClient('invalid url', '', '');
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
                }'
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = $this->getClient(['handler' => $stack]);
        $response = $client->sendRequest(new Request('GET', 'credits'));

        self::assertSame([
            'remaining' => '123.4343434343434343',
            'consumed' => '456.1212121212121212',
        ], $response);

        self::assertCount(1, $requestHistory);

        $request = $requestHistory[0]['request'];
        self::assertInstanceOf(Request::class, $request);
        self::assertEquals('https://example.com/credits', $request->getUri()->__toString());
        self::assertEquals('GET', $request->getMethod());
        self::assertEquals('authToken', $request->getHeader('authHeader')[0]);
        self::assertEquals('Billing PHP Client', $request->getHeader('User-Agent')[0]);
        self::assertEquals('application/json', $request->getHeader('Content-type')[0]);
    }

    public function testInvalidResponse(): void
    {
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                'invalid json'
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $client = $this->getClient(['handler' => $stack]);

        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('Unable to parse response body into JSON: Syntax error');
        $client->sendRequest(new Request('GET', 'credits'));
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
                }'
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
        $logger = new TestLogger();
        $client = $this->getClient(['handler' => $stack, 'logger' => $logger, 'userAgent' => 'test agent']);
        $client->sendRequest(new Request('GET', 'credits'));

        $request = $requestHistory[0]['request'];
        self::assertInstanceOf(Request::class, $request);
        self::assertEquals('test agent', $request->getHeader('User-Agent')[0]);
        self::assertTrue($logger->hasInfoThatContains('"GET  /1.1" 200 '));
        self::assertTrue($logger->hasInfoThatContains('test agent'));
    }

    public function testRetrySuccess(): void
    {
        $mock = new MockHandler([
            new Response(
                500,
                ['Content-Type' => 'application/json'],
                '{"message" => "Out of order"}'
            ),
            new Response(
                500,
                ['Content-Type' => 'application/json'],
                'Out of order'
            ),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                '{
                    "remaining": "123",
                    "consumed": "456"
                }'
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
        $client = $this->getClient(['handler' => $stack]);
        $response = $client->sendRequest(new Request('GET', 'credits'));

        self::assertSame([
            'remaining' => '123',
            'consumed' => '456',
        ], $response);

        self::assertCount(3, $requestHistory);
        $request = $requestHistory[0]['request'];
        self::assertInstanceOf(Request::class, $request);
        self::assertEquals('https://example.com/credits', $request->getUri()->__toString());
        $request = $requestHistory[1]['request'];
        self::assertEquals('https://example.com/credits', $request->getUri()->__toString());
        $request = $requestHistory[2]['request'];
        self::assertEquals('https://example.com/credits', $request->getUri()->__toString());
    }

    public function testRetryFailure(): void
    {
        $responses = [];
        for ($i = 0; $i < 30; $i++) {
            $responses[] = new Response(
                500,
                ['Content-Type' => 'application/json'],
                '{"message" => "Out of order"}'
            );
        }
        $mock = new MockHandler($responses);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
        $client = $this->getClient(['handler' => $stack, 'backoffMaxTries' => 1]);
        try {
            $client->sendRequest(new Request('GET', 'credits'));
            self::fail('Must throw exception');
        } catch (BillingException $e) {
            self::assertStringContainsString('500 Internal Server Error', $e->getMessage());
        }
        self::assertCount(2, $requestHistory);
    }

    public function testRetryFailureReducedBackoff(): void
    {
        $responses = [];
        for ($i = 0; $i < 30; $i++) {
            $responses[] = new Response(
                500,
                ['Content-Type' => 'application/json'],
                '{"message" => "Out of order"}'
            );
        }
        $mock = new MockHandler($responses);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
        $client = $this->getClient(['handler' => $stack, 'backoffMaxTries' => 3]);
        try {
            $client->sendRequest(new Request('GET', 'credits'));
            self::fail('Must throw exception');
        } catch (BillingException $e) {
            self::assertStringContainsString('500 Internal Server Error', $e->getMessage());
        }
        self::assertCount(4, $requestHistory);
    }
}
