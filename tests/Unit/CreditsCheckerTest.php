<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Unit;

use Generator;
use Keboola\BillingApi\Client;
use Keboola\BillingApi\ClientFactory;
use Keboola\BillingApi\CreditsChecker;
use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\Options\IndexOptions;
use PHPUnit\Framework\TestCase;

class CreditsCheckerTest extends TestCase
{
    public function testCheckCreditsNoBilling(): void
    {
        $storageApiClient = $this->createMock(StorageApiClient::class);
        $storageApiClient->method('indexAction')
            ->with($this->callback(function ($options) {
                self::assertInstanceOf(IndexOptions::class, $options);
                self::assertEquals(['components'], $options->getExclude());
                return true;
            }))
            ->willReturn([
                'services' => [
                    [
                        'id' => 'graph',
                        'url' => 'https://graph.keboola.com',
                    ],
                    [
                        'id' => 'encryption',
                        'url' => 'https://encryption.keboola.com',
                    ],
                ],
            ]);
        $creditsChecker = new CreditsChecker(new ClientFactory(), $storageApiClient);
        $this->assertTrue($creditsChecker->hasCredits());
    }

    public function testCheckCreditsNoFeature(): void
    {
        $storageApiClient = $this->getStorageApiMock(
            [
                'id' => '123',
                'owner' => [
                    'id' => '123',
                    'name' => 'test',
                    'features' => [
                        'feature1',
                    ],
                ],
            ],
        );

        $creditsChecker = new CreditsChecker(new ClientFactory(), $storageApiClient);
        self::assertTrue($creditsChecker->hasCredits());
    }

    public function valuesProvider(): Generator
    {
        yield [-123, false];
        yield [0, false];
        yield [0.0001, true];
        yield [1.0, true];
        yield [123, true];
    }

    /**
     * @dataProvider valuesProvider
     */
    public function testCheckCreditsHasFeatureHasCredits(float $remainingCredits, bool $hasCredits): void
    {
        $storageApiClient = $this->getStorageApiMock();
        $billingClient = $this->createMock(Client::class);
        $billingClient->expects(self::once())
            ->method('getRemainingCredits')
            ->willReturn($remainingCredits);
        $creditsChecker = self::getMockBuilder(CreditsChecker::class)
            ->setMethods(['getBillingClient'])
            ->setConstructorArgs([new ClientFactory(), $storageApiClient])
            ->getMock();
        $creditsChecker->method('getBillingClient')
            ->willReturn($billingClient);
        /** @var CreditsChecker $creditsChecker */
        self::assertEquals($hasCredits, $creditsChecker->hasCredits());
    }

    /**
     * @dataProvider valuesProvider
     */
    public function testCheckCreditsHasFeatureWithTopUp(float $remainingCredits, bool $hasCredits): void
    {
        $storageApiClient = $this->getStorageApiMock();

        $billingClient = $this->createMock(Client::class);
        $billingClient->expects(self::once())
            ->method('getRemainingCreditsWithOptionalTopUp')
            ->willReturn($remainingCredits);
        $creditsChecker = self::getMockBuilder(CreditsChecker::class)
            ->onlyMethods(['getBillingClient'])
            ->setConstructorArgs([new ClientFactory(), $storageApiClient])
            ->getMock();
        $creditsChecker->method('getBillingClient')
            ->willReturn($billingClient);
        /** @var CreditsChecker $creditsChecker */
        self::assertEquals($hasCredits, $creditsChecker->hasCredits(true));
    }

    public function testCheckCreditsWithCustomTimeout(): void
    {
        $billingClient = $this->createMock(Client::class);
        $billingClient->method('getRemainingCredits')->willReturn(1.0);

        $clientFactory = $this->createMock(ClientFactory::class);
        $clientFactory->expects(self::once())
            ->method('createClient')
            ->with('https://billing.keboola.com', 'boo', ['timeout' => 7.0])
            ->willReturn($billingClient)
        ;

        $creditsChecker = new CreditsChecker(
            $clientFactory,
            $this->getStorageApiMock(),
        );

        $hasCredits = $creditsChecker->hasCredits(
            clientOptions: [
                'timeout' => 7.0,
            ],
        );

        self::assertTrue($hasCredits);
    }

    private function getStorageApiMock(array $verifyTokenData = []): StorageApiClient
    {
        if (!$verifyTokenData) {
            $verifyTokenData = [
                'id' => '123',
                'owner' => [
                    'id' => '123',
                    'name' => 'test',
                    'features' => [
                        'feature1',
                        'pay-as-you-go',
                    ],
                ],
            ];
        }
        $storageApiClient = $this->createMock(StorageApiClient::class);
        $storageApiClient
            ->method('indexAction')
            ->with($this->callback(function ($options) {
                self::assertInstanceOf(IndexOptions::class, $options);
                self::assertEquals(['components'], $options->getExclude());
                return true;
            }))
            ->willReturn(
                [
                    'services' => [
                        [
                            'id' => 'encryption',
                            'url' => 'https://encryption.keboola.com',
                        ],
                        [
                            'id' => 'billing',
                            'url' => 'https://billing.keboola.com',
                        ],
                    ],
                ],
            );

        $storageApiClient->expects(self::once())
            ->method('verifyToken')
            ->willReturn($verifyTokenData);
        $storageApiClient->method('getTokenString')->willReturn('boo');
        return $storageApiClient;
    }
}
