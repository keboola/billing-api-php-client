<?php

declare(strict_types=1);

namespace Keboola\BillingApi;

use Closure;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Keboola\BillingApi\Exception\BillingException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Throwable;

/**
 * @phpstan-type Options array{
 *     handler?: (callable(RequestInterface, array): PromiseInterface),
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
    private const CONNECT_TIMEOUT = 10.0;
    private const CONNECT_RETRIES = 0;
    private const TRANSFER_TIMEOUT = 120.0;

    private GuzzleClient $guzzle;

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
        $this->guzzle = $this->initClient($billingUrl, $authHeaderName, $authToken, $options);
    }

    public function sendRequestWithResponse(Request $request): array
    {
        try {
            $response = $this->guzzle->send($request);

            $responseContents = $response->getBody()->getContents();
            if ($responseContents === '') {
                return [];
            }

            $data = (array) json_decode($responseContents, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new BillingException('Unable to parse response body into JSON: ' . json_last_error_msg());
            }
            return $data ?: [];
        } catch (GuzzleException $e) {
            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function sendRequestWithoutResponse(Request $request): void
    {
        try {
            $this->guzzle->send($request);
        } catch (GuzzleException $e) {
            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array{
     *     handler?: (callable(RequestInterface, array): PromiseInterface),
     *     backoffMaxTries: int<0, 100>,
     *     timeout?: null|float,
     *     connectTimeout?: null|float,
     *     userAgent: string,
     *     logger?: LoggerInterface
     * } $options
     */
    private function initClient(
        string $url,
        string $authHeaderName,
        string $authToken,
        array $options,
    ): GuzzleClient {
        // Initialize handlers (start with those supplied in constructor)
        // having HandlerStack inside HandlerStack seem weird, but it is needed so that middlewares already registered
        // on the passed handler are executed before middlewares registered here
        $handlerStack = HandlerStack::create($options['handler'] ?? null);

        // Set exponential backoff
        $handlerStack->push(Middleware::retry($this->createDefaultDecider($options['backoffMaxTries'])));

        // Set handler to set default headers
        $handlerStack->push(Middleware::mapRequest(
            function (RequestInterface $request) use ($authHeaderName, $authToken, $options) {
                return $request
                    ->withHeader('User-Agent', $options['userAgent'])
                    ->withHeader($authHeaderName, $authToken)
                    ->withHeader('Content-type', 'application/json');
            },
        ));

        // Set client logger
        if (isset($options['logger']) && $options['logger'] instanceof LoggerInterface) {
            $handlerStack->push(Middleware::log(
                $options['logger'],
                new MessageFormatter(
                    '{hostname} {req_header_User-Agent} - [{ts}] "{method} {resource} {protocol}/{version}"' .
                    ' {code} {res_header_Content-Length}',
                ),
            ));
        }

        // finally create the instance
        return new GuzzleClient(
            [
                'base_uri' => $url,
                'handler' => $handlerStack,
                'retries' => self::CONNECT_RETRIES,
                'connect_timeout' => $options['connectTimeout'] ?? self::CONNECT_TIMEOUT,
                'timeout' => $options['timeout'] ?? self::TRANSFER_TIMEOUT,
            ],
        );
    }

    private function createDefaultDecider(int $maxRetries): Closure
    {
        return function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?Throwable $error = null,
        ) use ($maxRetries) {
            if ($retries >= $maxRetries) {
                return false;
            } elseif ($response && $response->getStatusCode() >= 500) {
                return true;
            } elseif ($error && $error->getCode() >= 500) {
                return true;
            } else {
                return false;
            }
        };
    }
}
