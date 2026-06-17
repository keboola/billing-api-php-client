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

    public static function provideMissingManageToken(): iterable
    {
        yield 'null token' => ['authToken' => null];
        yield 'empty token' => ['authToken' => ''];
    }

    /**
     * @dataProvider provideMissingManageToken
     */
    public function testCreateManageClientWithoutTokenUsesServiceAccountAuth(?string $authToken): void
    {
        // No manage token -> service-account auth, which reads the projected SA token file at request
        // time. That file is absent in CI, so the read attempt surfaces as a BillingException naming the
        // SA token path -- which proves the service-account authenticator (not manage-token auth) was
        // selected. The SA happy path (real bearer header) can't be unit-tested: the path is hardcoded.
        $mock = new MockHandler([
            new Response(200, [], '{}'),
        ]);

        $client = (new ClientFactory())->createManageClient('https://example.com/', $authToken, [
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
