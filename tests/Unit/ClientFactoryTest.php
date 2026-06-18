<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Keboola\BillingApi\ClientFactory;
use Keboola\BillingApi\Exception\BillingException;
use PHPUnit\Framework\TestCase;
use Webmozart\Assert\InvalidArgumentException;

class ClientFactoryTest extends TestCase
{
    public function testCreateClientRejectsEmptyToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Storage API token must not be empty');
        // @phpstan-ignore-next-line we test passing an empty (non-`non-empty-string`) value
        (new ClientFactory())->createClient('https://example.com/', '');
    }

    public function testCreateManageClientRejectsEmptyToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Manage API token must not be empty');
        // @phpstan-ignore-next-line we test passing an empty (non-`non-empty-string`) value
        (new ClientFactory())->createManageClient('https://example.com/', '');
    }

    public function testCreateManageClientWithoutTokenUsesServiceAccountAuth(): void
    {
        // A null token -> service-account auth, which reads the projected SA token file at request
        // time. That file is absent in CI, so the read attempt surfaces as a BillingException naming the
        // SA token path -- which proves the service-account authenticator (not manage-token auth) was
        // selected. The SA happy path (real bearer header) can't be unit-tested: the path is hardcoded.
        $mock = new MockHandler([
            new Response(200, [], '{}'),
        ]);

        $client = (new ClientFactory())->createManageClient('https://example.com/', null, [
            'handler' => HandlerStack::create($mock),
            'backoffMaxTries' => 1, // minimise retry backoff; 0 is coerced to the default
        ]);

        $this->expectException(BillingException::class);
        $this->expectExceptionMessage('Service account token file');
        $client->recordJobDuration('project-id', 'job-id', 'keboola.component', 'standard', [], 1.0);
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
