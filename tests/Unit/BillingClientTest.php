<?php

namespace Tests\Keboola\BillingApi\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Keboola\BillingApi\BillingClient;
use Keboola\BillingApi\Exception\BillingClientException;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

class BillingClientTest extends TestCase
{
    /**
     * @return BillingClient
     */
    private function getClient(array $options)
    {
        return new BillingClient(
            'http://example.com/',
            'testToken',
            $options
        );
    }

    /**
     * @return void
     */
    public function testCreateClientInvalidBackoff()
    {
        self::expectException(BillingClientException::class);
        self::expectExceptionMessage(
            'Invalid parameters when creating client: Value "abc" is invalid: This value should be a valid number'
        );
        new BillingClient(
            'http://example.com/',
            'testToken',
            ['backoffMaxTries' => 'abc']
        );
    }

    /**
     * @return void
     */
    public function testCreateClientTooLowBackoff()
    {
        self::expectException(BillingClientException::class);
        self::expectExceptionMessage(
            'Invalid parameters when creating client: Value "-1" is invalid: This value should be between 0 and 100.'
        );
        new BillingClient(
            'http://example.com/',
            'testToken',
            ['backoffMaxTries' => -1]
        );
    }

    /**
     * @return void
     */
    public function testCreateClientTooHighBackoff()
    {
        self::expectException(BillingClientException::class);
        self::expectExceptionMessage(
            'Invalid parameters when creating client: Value "101" is invalid: This value should be between 0 and 100.'
        );
        new BillingClient(
            'http://example.com/',
            'testToken',
            ['backoffMaxTries' => 101]
        );
    }

    /**
     * @return void
     */
    public function testCreateClientInvalidToken()
    {
        self::expectException(BillingClientException::class);
        self::expectExceptionMessage(
            'Invalid parameters when creating client: Value "" is invalid: This value should not be blank.'
        );
        new BillingClient('http://example.com/', '');
    }

    /**
     * @return void
     */
    public function testCreateClientInvalidUrl()
    {
        self::expectException(BillingClientException::class);
        self::expectExceptionMessage(
            'Invalid parameters when creating client: Value "invalid url" is invalid: This value is not a valid URL.'
        );
        new BillingClient('invalid url', 'testToken');
    }

    /**
     * @return void
     */
    public function testCreateClientMultipleErrors()
    {
        self::expectException(BillingClientException::class);
        self::expectExceptionMessage(
            'Invalid parameters when creating client: Value "invalid url" is invalid: This value is not a valid URL.'
        );
        new BillingClient('invalid url', '');
    }

    /**
     * @return void
     */
    public function testClientRequestResponse()
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
        $credits = $client->getRemainingCredits();
        self::assertEquals(123.43434343434, $credits);
        self::assertCount(1, $requestHistory);
        /** @var Request $request */
        $request = $requestHistory[0]['request'];
        self::assertEquals('http://example.com/credits', $request->getUri()->__toString());
        self::assertEquals('GET', $request->getMethod());
        self::assertEquals('testToken', $request->getHeader('X-StorageApi-Token')[0]);
        self::assertEquals('Billing PHP Client', $request->getHeader('User-Agent')[0]);
        self::assertEquals('application/json', $request->getHeader('Content-type')[0]);
    }

    /**
     * @return void
     */
    public function testInvalidResponse()
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
        self::expectException(BillingClientException::class);
        self::expectExceptionMessage('Unable to parse response body into JSON: Syntax error');
        $client->getRemainingCredits();
    }

    /**
     * @return void
     */
    public function testLogger()
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
        $client->getRemainingCredits();
        /** @var Request $request */
        $request = $requestHistory[0]['request'];
        self::assertEquals('test agent', $request->getHeader('User-Agent')[0]);
        self::assertTrue($logger->hasInfoThatContains('"GET  /1.1" 200 '));
        self::assertTrue($logger->hasInfoThatContains('test agent'));
    }

    /**
     * @return void
     */
    public function testRetrySuccess()
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
        $credits = $client->getRemainingCredits();
        self::assertEquals('123', $credits);
        self::assertCount(3, $requestHistory);
        /** @var Request $request */
        $request = $requestHistory[0]['request'];
        self::assertEquals('http://example.com/credits', $request->getUri()->__toString());
        $request = $requestHistory[1]['request'];
        self::assertEquals('http://example.com/credits', $request->getUri()->__toString());
        $request = $requestHistory[2]['request'];
        self::assertEquals('http://example.com/credits', $request->getUri()->__toString());
    }

    /**
     * @return void
     */
    public function testRetryFailure()
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
            $client->getRemainingCredits();
            self::fail('Must throw exception');
        } catch (BillingClientException $e) {
            self::assertContains('500 Internal Server Error', $e->getMessage());
        }
        self::assertCount(2, $requestHistory);
    }

    /**
     * @return void
     */
    public function testRetryFailureReducedBackoff()
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
            $client->getRemainingCredits();
            self::fail('Must throw exception');
        } catch (BillingClientException $e) {
            self::assertContains('500 Internal Server Error', $e->getMessage());
        }
        self::assertCount(4, $requestHistory);
    }
}
