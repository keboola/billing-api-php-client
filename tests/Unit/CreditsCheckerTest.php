<?php

declare(strict_types=1);

namespace Tests\Keboola\BillingApi\Unit;

use Keboola\BillingApi\Client;
use Keboola\BillingApi\CreditsChecker;
use Keboola\StorageApi\Client as StorageApiClient;
use Keboola\StorageApi\Options\IndexOptions;
use PHPUnit\Framework\TestCase;

class CreditsCheckerTest extends TestCase
{
    /**
     * @return void
     */
    public function testCheckCreditsNoBilling()
    {
        $storageApiclient = $this->getMockBuilder(StorageApiClient::class)
            ->setMethods(['indexAction'])
            ->disableOriginalConstructor()
            ->getMock();
        $storageApiclient->method('indexAction')
            ->with($this->callback(function ($options) {
                /** @var IndexOptions $options */
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
        /** @var StorageApiClient $storageApiclient */
        $creditsChecker = new CreditsChecker($storageApiclient);
        $this->assertTrue($creditsChecker->hasCredits());
    }

    /**
     * @return void
     */
    public function testCheckCreditsNoFeature()
    {
        $storageApiclient = $this->getStorageApiMock(
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

        /** @var StorageApiClient $storageApiclient */
        $creditsChecker = new CreditsChecker($storageApiclient);
        self::assertTrue($creditsChecker->hasCredits());
    }

    /**
     * @return array
     */
    public function valuesProvider()
    {
        return [
            [-123, false],
            [0, false],
            [0.0001, true],
            [1.0, true],
            [123, true],
        ];
    }

    /**
     * @dataProvider valuesProvider
     * @param double $remainingCredits
     * @param bool $hasCredits
     * @return void
     */
    public function testCheckCreditsHasFeatureHasCredits($remainingCredits, $hasCredits)
    {
        $storageApiclient = $this->getStorageApiMock(
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

        $billingClient = self::getMockBuilder(Client::class)
            ->setMethods(['getRemainingCredits'])
            ->disableOriginalConstructor()
            ->getMock();
        $billingClient->method('getRemainingCredits')
            ->willReturn($remainingCredits);
        /** @var Client $storageApiclient */
        $creditsChecker = self::getMockBuilder(CreditsChecker::class)
            ->setMethods(['getBillingClient'])
            ->setConstructorArgs([$storageApiclient])
            ->getMock();
        $creditsChecker->method('getBillingClient')
            ->willReturn($billingClient);
        /** @var CreditsChecker $creditsChecker */
        self::assertEquals($hasCredits, $creditsChecker->hasCredits());
    }

    /**
     * @param array $indexData
     * @param array $verifyTokenData
     * @return StorageApiClient
     */
    private function getStorageApiMock(array $indexData, array $verifyTokenData): StorageApiClient
    {
        $storageApiclient = self::getMockBuilder(StorageApiClient::class)
            ->setMethods(['indexAction', 'verifyToken'])
            ->disableOriginalConstructor()
            ->getMock();

        $storageApiclient->expects($this->once())
            ->method('indexAction')
            ->with($this->callback(function ($options) {
                /** @var IndexOptions $options */
                self::assertInstanceOf(IndexOptions::class, $options);
                self::assertEquals(['components'], $options->getExclude());
                return true;
            }))
            ->willReturn($indexData);

        $storageApiclient->expects($this->once())
            ->method('verifyToken')
            ->willReturn($verifyTokenData);

        /** @var StorageApiClient $storageApiclient */
        return $storageApiclient;
    }
}
