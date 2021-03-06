<?php

namespace Keboola\BillingApi;

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

class Client
{
    const DEFAULT_USER_AGENT = 'Billing PHP Client';
    const DEFAULT_BACKOFF_RETRIES = 10;

    /** @var GuzzleClient */
    protected $guzzle;

    /**
     * @param string $billingUrl
     * @param string $storageToken
     * @param array $options
     */
    public function __construct(
        $billingUrl,
        $storageToken,
        array $options = []
    ) {
        $validator = Validation::createValidator();
        $errors = $validator->validate($billingUrl, [new Url()]);
        $errors->addAll(
            $validator->validate($billingUrl, [new NotBlank()])
        );
        $errors->addAll(
            $validator->validate($storageToken, [new NotBlank()])
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
        $this->guzzle = $this->initClient($billingUrl, $storageToken, $options);
    }

    /**
     * @return float
     */
    public function getRemainingCredits()
    {
        $request = new Request('GET', 'credits', []);
        $data = $this->sendRequest($request);
        return (double) $data['remaining'];
    }

    /**
     * @param int $maxRetries
     * @return \Closure
     */
    private function createDefaultDecider($maxRetries)
    {
        return function (
            $retries,
            RequestInterface $request,
            ResponseInterface $response = null,
            $error = null
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

    /**
     * @param string $url
     * @param string $token
     * @param array $options
     * @return GuzzleClient
     */
    private function initClient($url, $token, array $options = [])
    {
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
            function (RequestInterface $request) use ($token, $options) {
                return $request
                    ->withHeader('User-Agent', $options['userAgent'])
                    ->withHeader('X-StorageApi-Token', $token)
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
        return new GuzzleClient(['base_uri' => $url, 'handler' => $handlerStack]);
    }

    /**
     * @return array
     */
    private function sendRequest(Request $request)
    {
        try {
            $response = $this->guzzle->send($request);
            $data = json_decode($response->getBody()->getContents(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new BillingException('Unable to parse response body into JSON: ' . json_last_error_msg());
            }
            return $data ?: [];
        } catch (GuzzleException $e) {
            throw new BillingException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
