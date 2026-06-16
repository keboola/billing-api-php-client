<?php

declare(strict_types=1);

namespace Keboola\BillingApi;

use Closure;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use Keboola\ApiClientBase\ApiClient;
use Keboola\ApiClientBase\ApiClientOptions;
use Keboola\ApiClientBase\Exception\ClientException;
use Keboola\BillingApi\Auth\HeaderTokenAuthenticator;
use Keboola\BillingApi\Exception\BillingException;
use Keboola\BillingApi\Model\ArrayResponse;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

/**
 * @phpstan-type Options array{
 *     handler?: HandlerStack|Closure,
 *     backoffMaxTries?: int<0, 100>,
 *     timeout?: null|float,
 *     connectTimeout?: null|float,
 *     userAgent?: string,
 *     logger?: LoggerInterface
 *  }
 */
class InternalClient
{
    private const DEFAULT_USER_AGENT = 'Billing PHP Client';
    private const DEFAULT_BACKOFF_RETRIES = 10;

    private ApiClient $apiClient;

    /**
     * @param Options $options
     */
    public function __construct(
        string $billingUrl,
        string $authHeaderName,
        string $authToken,
        array $options = [],
    ) {
        $validator = Validation::createValidator();
        $errors = $validator->validate($billingUrl, [new Url()]);
        $errors->addAll(
            $validator->validate($billingUrl, [new NotBlank()]),
        );
        $errors->addAll(
            $validator->validate($authHeaderName, [new NotBlank()]),
        );
        $errors->addAll(
            $validator->validate($authToken, [new NotBlank()]),
        );
        if (!empty($options['backoffMaxTries'])) {
            $errors->addAll($validator->validate($options['backoffMaxTries'], [new Range(['min' => 0, 'max' => 100])]));
            $options['backoffMaxTries'] = intval($options['backoffMaxTries']);
        } else {
            $options['backoffMaxTries'] = self::DEFAULT_BACKOFF_RETRIES;
        }
        if (empty($options['userAgent'])) {
            $options['userAgent'] = self::DEFAULT_USER_AGENT;
        }
        if ($errors->count() !== 0) {
            $messages = '';
            /** @var ConstraintViolationInterface $error */
            foreach ($errors as $error) {
                $messages .= 'Value "' . $error->getInvalidValue() . '" is invalid: ' . $error->getMessage() . "\n";
            }
            throw new BillingException('Invalid parameters when creating client: ' . $messages);
        }

        // The NotBlank validation above guarantees a non-empty URL; narrow the type for the base client.
        assert($billingUrl !== '');

        $this->apiClient = new ApiClient(
            $billingUrl,
            new HeaderTokenAuthenticator($authHeaderName, $authToken),
            new ApiClientOptions(
                userAgent: $options['userAgent'],
                backoffMaxTries: $options['backoffMaxTries'],
                connectTimeout: (int) ($options['connectTimeout'] ?? ApiClientOptions::DEFAULT_CONNECT_TIMEOUT),
                requestTimeout: (int) ($options['timeout'] ?? ApiClientOptions::DEFAULT_REQUEST_TIMEOUT),
                requestHandler: $options['handler'] ?? null,
                logger: $options['logger'] ?? null,
            ),
        );
    }

    public function sendRequestWithResponse(Request $request): array
    {
        try {
            return $this->apiClient->sendRequestAndMapResponse(
                $this->withJsonContentType($request),
                ArrayResponse::class,
            )->data;
        } catch (ClientException $e) {
            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function sendRequestWithoutResponse(Request $request): void
    {
        try {
            $this->apiClient->sendRequest($this->withJsonContentType($request));
        } catch (ClientException $e) {
            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function withJsonContentType(Request $request): RequestInterface
    {
        return $request->withHeader('Content-Type', 'application/json');
    }
}
