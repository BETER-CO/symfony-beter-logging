<?php

namespace Beter\Bundle\BeterLoggingBundle\EventSubscriber;

use Beter\Bundle\BeterLoggingBundle\Exception\HandlerException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class HttpSubscriber implements EventSubscriberInterface
{
    public const LOG_CATEGORY = __CLASS__;
    public const MIN_ALLOWED_VALUE_FOR_MAX_HEADER_VALUE_LENGTH = 1;
    public const MASKED = '[Masked]';
    public const TRUNCATED = '[Truncated]';
    public const SANITIZE_BODY_PARAMS_MAX_DEPTH = 3;

    /**
     * Each key represent excluded route, value is always (int) 1.
     *
     * This approach speed up search. You may use isset() instead of in_array. O(1) vs O(n).
     *
     * @var array
     */
    protected array $excludedRoutes = [];

    protected int $maxHeaderValueLength = 256;

    /**
     * Each key represent header name, value is always (int) 1.
     *
     * This approach speed up search. You may use isset() instead of in_array. O(1) vs O(n).
     *
     * @var array
     */
    protected array $headersToMask = [
        'cookie' => 1,
        'x-forwarded-for' => 1,
        'x-csrf-token' => 1,
    ];

    /**
     * List of POST param patterns to check for the masking.
     *
     * @var array
     */
    protected array $postParamPatternsToMask = [
        '/password/i',
        '/csrf/i',
    ];

    private TokenStorageInterface $tokenStorage;
    private LoggerInterface $logger;

    public function __construct(TokenStorageInterface $tokenStorage, LoggerInterface $logger)
    {
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($this->isRouteExcluded($request)) {
            return;
        }

        if ($request->attributes->has('_controller')) {
            // routing is already done
            return;
        }

        $context = [
            'user' => [],
            'request' => [],
            'headers' => [],
        ];

        try {
//            $user = $this->tokenStorage->getToken()?->getUser();
            $user = $this->tokenStorage->getToken()->getUser();
            $context['user']['id'] = $user && method_exists($user, 'getId') ? $user->getId(): '0';
            $context['user']['username'] = $user ? $user->getUserIdentifier() : '[guest]';
            $context['request']['method'] = $request->getMethod();
            $context['request']['absoluteUrl'] = $request->getRequestUri();
            $context['request']['bodyParams'] = $this->sanitizeBodyParams($request->request->all());
            $context['request']['userIp'] = $request->getClientIp();
            $context['headers'] = $this->sanitizeHeaders($request->headers->all());
        } catch (\Throwable $t) {
            $this->logger->error(new HandlerException('An error occurred during the context gathering', $context, $t));
        }

        $this->logger->info('Incoming request', $context);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if ($this->isRouteExcluded($request)) {
            return;
        }

        $context = [];
        try {
            $response = $event->getResponse();

            $context['request'] = [
                'method' => $request->getMethod(),
                'absoluteUrl' => $request->getUri(),
            ];

            $context['response'] = [
                'statusCode' => $response->getStatusCode(),
                'format' => $response->headers->get('Content-Type'),
                'isStream' => $response instanceof StreamedResponse,
                'contentLength' => !$response instanceof StreamedResponse ? null : mb_strlen($response->getContent(), '8bit'),
                'headers' => $this->sanitizeHeaders($response->headers->all())
            ];

            $context['execTimeSec'] = microtime(true) - $request->server->get('REQUEST_TIME');
            $context['memoryPeakUsageBytes'] = memory_get_peak_usage(true);
        } catch (\Throwable $t) {
            $this->logger->error(new HandlerException('An error occurred during the context gathering', $context, $t));
        }

        $this->logger->info('Outgoing response', $context);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    private function isRouteExcluded(Request $request): bool
    {
        /**
         * $request->resolve() call is pretty expensive, so we may skip that call if there is nothing
         * to exclude.
         */
        if (empty($this->excludedRoutes)) {
            return false;
        }

        try {
            $route = $request->getUri();
            return isset($this->excludedRoutes[$route]);
        } catch (\Throwable $t) {
            // $request->resolve may generate NotFoundHttpException when it can't parse url. It's not
            // the situation when the route is not found!
        }

        return false;
    }

    private function toFlatArray(array $headers): array
    {
        $flatArray = [];
        $duplicatedHeaders = false;

        foreach ($headers as $headerName => $headerValues) {
            // yii allows to set few header values for the same header name O_o
            if (count($headerValues) > 1) {
                $duplicatedHeaders = true;
            }

            // process only the first value
            $flatArray[$headerName] = $headerValues[0];
        }

        if ($duplicatedHeaders) {
            $e = new RuntimeException(
                'Few header values was found for the same header name. Check an attached context.',
                ['headers' => $headers]
            );
            $this->logger->warning($e);
        }

        return $flatArray;
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = $this->toFlatArray($headers);

        foreach ($sanitized as $name => $value) {
            // for small arrays in_array is faster than isset
            if (isset($this->headersToMask[$name])) {
                $sanitized[$name] = static::MASKED;
                continue;
            }

            if (mb_strlen($value) > $this->maxHeaderValueLength) {
                $length = $this->maxHeaderValueLength - strlen(static::TRUNCATED);
                if ($length > 0) {
                    $sanitized[$name] = mb_substr($value, 0, $length) . '...' . static::TRUNCATED;
                } else {
                    // strange situation, but we need to comply our settings ;)
                    $sanitized[$name] = static::TRUNCATED;
                }
            }
        }

        return $sanitized;
    }

    private function matchPostParamPatternsToMask(string $paramName): bool
    {
        foreach ($this->postParamPatternsToMask as $pattern) {
            if (preg_match($pattern, $paramName) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Masks or truncates body param values.
     */
    private function sanitizeBodyParams(mixed $bodyParams, int $depth = 0): mixed
    {
        if (!is_array($bodyParams)) {
            // yii\web\Request::getBodyParams() method returns array or object
            // I don't have an idea when object may be returned and what type of object it will be.
            return $bodyParams;
        }

        if (static::SANITIZE_BODY_PARAMS_MAX_DEPTH <= $depth) {
            return static::TRUNCATED;
        }

        $sanitized = [];
        foreach ($bodyParams as $name => $value) {
            if ($this->matchPostParamPatternsToMask($name)) {
                $sanitized[$name] = static::MASKED;
            } else {
                $sanitized[$name] = $this->sanitizeBodyParams($value, $depth + 1);
            }
        }

        return $sanitized;
    }

    private function prepareSanitizedHeaderNames(): self
    {
        $this->headersToMask = array_change_key_case($this->headersToMask);

        return $this;
    }
}
