<?php
namespace Mervit\iDoklad;

use Mervit\iDoklad\Exceptions\IDokladException;

require_once __DIR__ . '/Exceptions/IDokladException.php';
require_once __DIR__ . '/Endpoint.php';

class Client
{
    /** @var string */
    protected $apiUrl;

    /** @var string|null */
    protected $clientId;

    /** @var string|null */
    protected $clientSecret;

    /** @var string */
    protected $scope = 'idoklad_api';

    /** @var callable|null */
    protected $httpHandler;

    /** @var callable|null */
    protected $logger;

    /** @var string|null */
    protected $accessToken;

    /** @var int|null */
    protected $tokenExpiresAt;

    /** @var array<string,mixed> */
    protected $lastResponseInfo = [];

    /** @var array<string,mixed> */
    protected $options = [];

    /** @var string|null */
    protected $userId;

    public function __construct($apiUrl, array $config = [])
    {
        $this->apiUrl = rtrim($apiUrl ?: 'https://api.idoklad.cz/api/v3', '/');
        $this->clientId = $config['client_id'] ?? null;
        $this->clientSecret = $config['client_secret'] ?? null;
        $this->scope = $config['scope'] ?? $this->scope;
        $this->httpHandler = $config['http_handler'] ?? null;
        $this->logger = $config['logger'] ?? null;
        $this->userId = $config['user_id'] ?? null;
        $this->options = $config + [
            'timeout' => $config['timeout'] ?? 30,
            'sslverify' => array_key_exists('sslverify', $config) ? $config['sslverify'] : true,
            'token_url' => $config['token_url'] ?? 'https://app.idoklad.cz/identity/server/connect/token',
            'user_agent' => $config['user_agent'] ?? 'iDoklad-Helper/2.0',
            'token_cache' => $config['token_cache'] ?? null,
        ];
    }

    /**
     * Retrieve an access token, caching results when possible.
     *
     * @param bool $forceRefresh
     * @return string
     * @throws IDokladException
     */
    public function getAccessToken($forceRefresh = false)
    {
        if (!$forceRefresh && $this->accessToken && $this->tokenExpiresAt && $this->tokenExpiresAt > (time() + 60)) {
            return $this->accessToken;
        }

        if (!$forceRefresh && $this->loadTokenFromCache()) {
            return $this->accessToken;
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new IDokladException('iDoklad client credentials are missing.');
        }

        $tokenUrl = $this->options['token_url'];
        $body = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => $this->scope,
        ];

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
            'User-Agent' => $this->options['user_agent'],
        ];

        $args = [
            'body' => http_build_query($body),
            'headers' => $headers,
            'timeout' => $this->options['timeout'],
            'sslverify' => $this->options['sslverify'],
        ];

        $this->log('Requesting iDoklad access token', ['url' => $tokenUrl, 'client_id' => $this->clientId]);

        $response = $this->sendHttpRequest('POST', $tokenUrl, $args);
        $decoded = $this->interpretResponse($response, $tokenUrl, 'POST', $args, false);

        if (!is_array($decoded) || !isset($decoded['access_token'])) {
            throw new IDokladException('Invalid OAuth response from iDoklad.', 0, null, ['response' => $decoded]);
        }

        $expiresIn = isset($decoded['expires_in']) ? (int) $decoded['expires_in'] : 3600;
        $this->accessToken = $decoded['access_token'];
        $this->tokenExpiresAt = time() + $expiresIn;

        $this->persistTokenToCache($this->accessToken, $this->tokenExpiresAt);

        return $this->accessToken;
    }

    /**
     * Perform an API request.
     *
     * @param string $method
     * @param string $endpoint
     * @param array<string,mixed> $options
     * @return mixed
     * @throws IDokladException
     */
    public function request($method, $endpoint, array $options = [])
    {
        $method = strtoupper($method);
        $endpoint = '/' . ltrim($endpoint, '/');

        $query = isset($options['query']) ? (array) $options['query'] : [];
        $headers = isset($options['headers']) ? (array) $options['headers'] : [];
        $timeout = $options['timeout'] ?? $this->options['timeout'];
        $decode = array_key_exists('decode', $options) ? (bool) $options['decode'] : true;

        unset($options['query'], $options['headers'], $options['timeout'], $options['decode']);

        $body = null;
        if (isset($options['json'])) {
            $body = json_encode($options['json']);
            $headers['Content-Type'] = 'application/json';
        } elseif (isset($options['body'])) {
            $body = $options['body'];
        }

        $url = $this->buildUrl($endpoint, $query);

        $headers = $this->prepareHeaders($headers);
        $args = [
            'headers' => $headers,
            'timeout' => $timeout,
            'sslverify' => $this->options['sslverify'],
        ];

        if ($body !== null) {
            $args['body'] = $body;
        }

        $response = $this->sendHttpRequest($method, $url, $args);
        $decoded = $this->interpretResponse($response, $url, $method, $args, $decode);

        return $decoded;
    }

    /**
     * Provide fluent access to API endpoints using camelCase helpers.
     *
     * @param string $name
     * @param array<int,mixed> $arguments
     * @return Endpoint
     */
    public function __call($name, $arguments)
    {
        return $this->endpoint($this->normalizeResourceName($name));
    }

    /**
     * Manually request an endpoint wrapper.
     *
     * @param string $resource
     * @return Endpoint
     */
    public function endpoint($resource)
    {
        return new Endpoint($this, $resource);
    }

    /**
     * Expose information about the last HTTP interaction.
     *
     * @return array<string,mixed>
     */
    public function getLastResponseInfo()
    {
        return $this->lastResponseInfo;
    }

    /**
     * Assign a custom logger callable.
     *
     * @param callable|null $logger
     * @return void
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Normalize a camelCase method name into PascalCase resource.
     *
     * @param string $name
     * @return string
     */
    protected function normalizeResourceName($name)
    {
        if (strpos($name, '_') !== false) {
            $parts = explode('_', $name);
            $parts = array_map('ucfirst', $parts);
            return implode('', $parts);
        }

        $resource = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);
        $resource = str_replace(' ', '', ucwords($resource));

        return $resource;
    }

    /**
     * Merge provided headers with authorization defaults.
     *
     * @param array<string,string> $headers
     * @return array<string,string>
     * @throws IDokladException
     */
    protected function prepareHeaders(array $headers)
    {
        $headers = array_change_key_case($headers, CASE_LOWER);
        $headers['authorization'] = 'Bearer ' . $this->getAccessToken();
        $headers['accept'] = $headers['accept'] ?? 'application/json';
        $headers['user-agent'] = $headers['user-agent'] ?? $this->options['user_agent'];

        return $headers;
    }

    /**
     * Build the full URL to the API endpoint.
     *
     * @param string $endpoint
     * @param array<string,mixed> $query
     * @return string
     */
    protected function buildUrl($endpoint, array $query = [])
    {
        $url = $this->apiUrl . $endpoint;

        if (!empty($query)) {
            $separator = (strpos($url, '?') === false) ? '?' : '&';
            $url .= $separator . http_build_query($query);
        }

        return $url;
    }

    /**
     * Send the HTTP request, using WordPress HTTP API when available.
     *
     * @param string $method
     * @param string $url
     * @param array<string,mixed> $args
     * @return mixed
     */
    protected function sendHttpRequest($method, $url, array $args)
    {
        if ($this->httpHandler && is_callable($this->httpHandler)) {
            return call_user_func($this->httpHandler, $method, $url, $args);
        }

        if (!function_exists('wp_remote_request')) {
            throw new IDokladException('WordPress HTTP API is not available. Provide a custom http_handler.');
        }

        $args['method'] = $method;

        return wp_remote_request($url, $args);
    }

    /**
     * Interpret the HTTP response, populating lastResponseInfo and throwing detailed errors.
     *
     * @param mixed $response
     * @param string $url
     * @param string $method
     * @param array<string,mixed> $args
     * @param bool $decodeJson
     * @return mixed
     * @throws IDokladException
     */
    protected function interpretResponse($response, $url, $method, array $args, $decodeJson)
    {
        if (function_exists('is_wp_error') && is_wp_error($response)) {
            $message = $response->get_error_message();
            throw new IDokladException('HTTP request failed: ' . $message, 0, null, ['url' => $url, 'method' => $method]);
        }

        $statusCode = null;
        $body = null;
        $headers = [];

        if (is_array($response)) {
            if (isset($response['response']['code'])) {
                $statusCode = (int) $response['response']['code'];
            } elseif (isset($response['code'])) {
                $statusCode = (int) $response['code'];
            }

            if (isset($response['body'])) {
                $body = $response['body'];
            }

            if (isset($response['headers'])) {
                $headers = is_object($response['headers']) && method_exists($response['headers'], 'getAll')
                    ? $response['headers']->getAll()
                    : (array) $response['headers'];
            }
        }

        if ($statusCode === null && function_exists('wp_remote_retrieve_response_code')) {
            $statusCode = (int) wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $wpHeaders = wp_remote_retrieve_headers($response);
            if (is_object($wpHeaders) && method_exists($wpHeaders, 'getAll')) {
                $headers = $wpHeaders->getAll();
            }
        }

        if ($body === null && isset($response['body'])) {
            $body = $response['body'];
        }

        if ($statusCode === null) {
            throw new IDokladException('Unable to determine HTTP status code from response.', 0, null, ['response' => $response]);
        }

        $this->lastResponseInfo = [
            'status_code' => $statusCode,
            'body' => $body,
            'headers' => $headers,
            'url' => $url,
            'method' => $method,
            'args' => $args,
        ];

        if ($statusCode >= 400) {
            $message = $this->buildErrorMessage($statusCode, $body);
            throw new IDokladException($message, $statusCode, null, [
                'response_body' => $body,
                'response_headers' => $headers,
                'url' => $url,
                'method' => $method,
            ]);
        }

        if (!$decodeJson) {
            return $body;
        }

        if ($body === '' || $body === null) {
            return null;
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $body;
        }

        return $decoded;
    }

    /**
     * Generate a readable error message from API responses.
     *
     * @param int $status
     * @param string|null $body
     * @return string
     */
    protected function buildErrorMessage($status, $body)
    {
        $message = 'iDoklad API request failed with status ' . $status;
        if ($body) {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (isset($decoded['Message'])) {
                    $message .= ': ' . $decoded['Message'];
                } elseif (isset($decoded['message'])) {
                    $message .= ': ' . $decoded['message'];
                } elseif (isset($decoded['error_description'])) {
                    $message .= ': ' . $decoded['error_description'];
                }

                if (isset($decoded['ModelState']) && is_array($decoded['ModelState'])) {
                    $details = [];
                    foreach ($decoded['ModelState'] as $field => $errors) {
                        if (is_array($errors)) {
                            $details[] = $field . ': ' . implode(', ', $errors);
                        }
                    }

                    if (!empty($details)) {
                        $message .= ' | ' . implode(' | ', $details);
                    }
                }
            } else {
                $truncate = function_exists('mb_substr') ? mb_substr($body, 0, 500) : substr($body, 0, 500);
                $message .= ' | Response body: ' . $truncate;
            }
        }

        return $message;
    }

    /**
     * Attempt to hydrate access token data from cache mechanisms.
     *
     * @return bool
     */
    protected function loadTokenFromCache()
    {
        $cacheKey = $this->getTokenCacheKey();
        $cache = $this->options['token_cache'];

        if (is_array($cache) && isset($cache['get']) && is_callable($cache['get'])) {
            $data = call_user_func($cache['get'], $cacheKey);
            if ($this->hydrateToken($data)) {
                return true;
            }
        }

        if (function_exists('get_transient')) {
            $data = get_transient($cacheKey);
            if ($this->hydrateToken($data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Persist access token using available cache helpers.
     *
     * @param string $token
     * @param int $expiresAt
     * @return void
     */
    protected function persistTokenToCache($token, $expiresAt)
    {
        $cacheKey = $this->getTokenCacheKey();
        $data = [
            'token' => $token,
            'expires_at' => $expiresAt,
        ];

        $cache = $this->options['token_cache'];
        if (is_array($cache) && isset($cache['set']) && is_callable($cache['set'])) {
            call_user_func($cache['set'], $cacheKey, $data, max(60, $expiresAt - time()));
        }

        if (function_exists('set_transient')) {
            $expiration = max(60, $expiresAt - time());
            set_transient($cacheKey, $data, $expiration);
        }
    }

    /**
     * Populate the token properties if cache data is valid.
     *
     * @param mixed $data
     * @return bool
     */
    protected function hydrateToken($data)
    {
        if (!is_array($data) || empty($data['token']) || empty($data['expires_at'])) {
            return false;
        }

        if ((int) $data['expires_at'] <= time() + 30) {
            return false;
        }

        $this->accessToken = $data['token'];
        $this->tokenExpiresAt = (int) $data['expires_at'];

        return true;
    }

    /**
     * Build cache key for token storage.
     *
     * @return string
     */
    protected function getTokenCacheKey()
    {
        $parts = [$this->clientId, $this->scope, $this->userId];
        return 'idoklad_token_' . md5(implode('|', array_filter($parts)));
    }

    /**
     * Simple logger helper.
     *
     * @param string $message
     * @param array<string,mixed> $context
     * @return void
     */
    protected function log($message, array $context = [])
    {
        if ($this->logger && is_callable($this->logger)) {
            call_user_func($this->logger, $message, $context);
        }
    }
}
