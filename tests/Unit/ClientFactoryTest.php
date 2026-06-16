<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Keboola\BillingApi\ClientFactory;
use Keboola\BillingApi\Exception\BillingException;
use PHPUnit\Framework\TestCase;

class ClientFactoryTest extends TestCase
{
    public function testCreateClientRejectsEmptyToken(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('token must not be empty');
        (new ClientFactory())->createClient('https://example.com/', '');
    }

    public function testCreateManageClientRejectsEmptyToken(): void
    {
        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('token must not be empty');
        (new ClientFactory())->createManageClient('https://example.com/', '');
    }

    public function testCreateClientAuthenticatesWithStorageApiToken(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"remaining": "1", "consumed": "0"}'),
        ]);

        $client = (new ClientFactory())->createClient('https://example.com/', 'storage-token', [
            'handler' => HandlerStack::create($mock),
        ]);
        $client->getRemainingCredits();

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('storage-token', $request->getHeaderLine('X-StorageApi-Token'));
        self::assertSame('', $request->getHeaderLine('X-KBC-ManageApiToken'));
    }

    public function testCreateManageClientAuthenticatesWithManageApiToken(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{}'),
        ]);

        $client = (new ClientFactory())->createManageClient('https://example.com/', 'manage-token', [
            'handler' => HandlerStack::create($mock),
        ]);
        $client->recordJobDuration('project-id', 'job-id', 'keboola.component', 'standard', [], 1.0);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('manage-token', $request->getHeaderLine('X-KBC-ManageApiToken'));
        self::assertSame('', $request->getHeaderLine('X-StorageApi-Token'));
    }
}
