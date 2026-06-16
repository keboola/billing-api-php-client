<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Keboola\BillingApi\Client;
use Keboola\BillingApi\InternalClient;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testGetRemainingCredits(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '
                {
                    "remaining": "123.4343434343434343",
                    "consumed": "456.1212121212121212"
                }
            '),
        ]);
        $internalClient = new InternalClient('http://example.com', 'auth-header', 'dummy-token', [
            'handler' => HandlerStack::create($mock),
        ]);

        $client = new Client($internalClient);

        $result = $client->getRemainingCredits();

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('http://example.com/credits', (string) $request->getUri());
        self::assertSame('dummy-token', $request->getHeaderLine('auth-header'));

        self::assertEqualsWithDelta(123.43434343434, $result, 0.00001);
    }

    public function testGetRemainingCreditsWithTopUp(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '
                {
                    "remaining": "123.4343434343434343",
                    "consumed": "456.1212121212121212"
                }
            '),
        ]);
        $internalClient = new InternalClient('http://example.com', 'auth-header', 'dummy-token', [
            'handler' => HandlerStack::create($mock),
        ]);

        $client = new Client($internalClient);

        $result = $client->getRemainingCreditsWithOptionalTopUp();

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://example.com/credits', (string) $request->getUri());
        self::assertSame('dummy-token', $request->getHeaderLine('auth-header'));

        self::assertEqualsWithDelta(123.43434343434, $result, 0.00001);
    }
}
