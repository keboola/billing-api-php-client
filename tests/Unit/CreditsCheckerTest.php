<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Unit;

use Generator;
use Keboola\BillingApi\Client;
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
        $creditsChecker = new CreditsChecker($storageApiClient);
        $this->assertTrue($creditsChecker->hasCredits());
    }

    public function testCheckCreditsNoFeature(): void
    {
        $storageApiClient = $this->getStorageApiMock(
            [
                'services' => [
                    [
                        'id' => 'graph',
                        'url' => 'https://graph.keboola.com',
                    ],
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
            [
                'id' => '123',
                'owner' => [
                    'id' => '123',
                    'name' => 'test',
                    'features' => [
                        'transformation-config-storage',
                    ],
                ],
            ]
        );

        $creditsChecker = new CreditsChecker($storageApiClient);
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
        $storageApiClient = $this->getStorageApiMock(
            [
                'services' => [
                    [
                        'id' => 'graph',
                        'url' => 'https://graph.keboola.com',
                    ],
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
            [
                'id' => '123',
                'owner' => [
                    'id' => '123',
                    'name' => 'test',
                    'features' => [
                        'transformation-config-storage',
                        'pay-as-you-go',
                    ],
                ],
            ]
        );

        $billingClient = $this->createMock(Client::class);
        $billingClient->method('getRemainingCredits')->willReturn($remainingCredits);
        $creditsChecker = self::getMockBuilder(CreditsChecker::class)
            ->setMethods(['getBillingClient'])
            ->setConstructorArgs([$storageApiClient])
            ->getMock();
        $creditsChecker->method('getBillingClient')
            ->willReturn($billingClient);
        /** @var CreditsChecker $creditsChecker */
        self::assertEquals($hasCredits, $creditsChecker->hasCredits());
    }

    private function getStorageApiMock(array $indexData, array $verifyTokenData): StorageApiClient
    {
        $storageApiClient = $this->createMock(StorageApiClient::class);
        $storageApiClient->expects(self::once())
            ->method('indexAction')
            ->with($this->callback(function ($options) {
                self::assertInstanceOf(IndexOptions::class, $options);
                self::assertEquals(['components'], $options->getExclude());
                return true;
            }))
            ->willReturn($indexData);

        $storageApiClient->expects(self::once())
            ->method('verifyToken')
            ->willReturn($verifyTokenData);
        return $storageApiClient;
    }
}
