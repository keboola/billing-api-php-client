<?php

declare(strict_types=1);

namespace Keboola\BillingApi;

use Closure;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
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

class InternalClient
{
    private const DEFAULT_USER_AGENT = 'Billing PHP Client';
    private const DEFAULT_BACKOFF_RETRIES = 10;
    private const CONNECT_TIMEOUT = 10;
    private const CONNECT_RETRIES = 0;
    private const TRANSFER_TIMEOUT = 120;

    private GuzzleClient $guzzle;

    public function __construct(
        string $billingUrl,
        string $authHeaderName,
        string $authToken,
        array $options = []
    ) {
        $validator = Validation::createValidator();
        $errors = $validator->validate($billingUrl, [new Url()]);
        $errors->addAll(
            $validator->validate($billingUrl, [new NotBlank()])
        );
        $errors->addAll(
            $validator->validate($authHeaderName, [new NotBlank()])
        );
        $errors->addAll(
            $validator->validate($authToken, [new NotBlank()])
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

    public function sendRequest(Request $request): array
    {
        try {
            $response = $this->guzzle->send($request);
            $data = (array) json_decode($response->getBody()->getContents(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new BillingException('Unable to parse response body into JSON: ' . json_last_error_msg());
            }
            return $data ?: [];
        } catch (GuzzleException $e) {
            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }

    private function initClient(
        string $url,
        string $authHeaderName,
        string $authToken,
        array $options = []
    ): GuzzleClient {
        // Initialize handlers (start with those supplied in constructor)
        if (isset($options['handler']) && is_callable($options['handler'])) {
            $handlerStack = HandlerStack::create($options['handler']);
        } else {
            $handlerStack = HandlerStack::create();
        }
        // Set exponential backoff
        $handlerStack->push(Middleware::retry($this->createDefaultDecider($options['backoffMaxTries'])));
        // Set handler to set default headers
        $handlerStack->push(Middleware::mapRequest(
            function (RequestInterface $request) use ($authHeaderName, $authToken, $options) {
                return $request
                    ->withHeader('User-Agent', $options['userAgent'])
                    ->withHeader($authHeaderName, $authToken)
                    ->withHeader('Content-type', 'application/json');
            }
        ));
        // Set client logger
        if (isset($options['logger']) && $options['logger'] instanceof LoggerInterface) {
            $handlerStack->push(Middleware::log(
                $options['logger'],
                new MessageFormatter(
                    '{hostname} {req_header_User-Agent} - [{ts}] "{method} {resource} {protocol}/{version}"' .
                    ' {code} {res_header_Content-Length}'
                )
            ));
        }
        // finally create the instance
        return new GuzzleClient(
            [
                'base_uri' => $url,
                'handler' => $handlerStack,
                'retries' => self::CONNECT_RETRIES,
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout' => self::TRANSFER_TIMEOUT,
            ]
        );
    }

    private function createDefaultDecider(int $maxRetries): Closure
    {
        return function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?Throwable $error = null
        ) use ($maxRetries) {
            if ($retries >= $maxRetries) {
                return false;
            } elseif ($response && $response->getStatusCode() >= 500) {
                return true;
            } elseif ($error) {
                return true;
            } else {
                return false;
            }
        };
    }
}
