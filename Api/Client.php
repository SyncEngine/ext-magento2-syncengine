<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Api;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;

class Client
{
    private const CACHE_STATUS = 'syncengine_api_status';
    private const CACHE_ENDPOINTS = 'syncengine_api_endpoints';

    private string $host;
    private string $token;
    private array $options;
    private string $root;

    public function __construct(
        string $host,
        string $token,
        array $options,
        private readonly Curl $curl,
        private readonly CacheInterface $cache,
        private readonly Json $serializer
    ) {
        $this->host = rtrim(trim($host), '/');
        $this->token = trim($token);
        $this->options = $options;
        if (!array_key_exists('version', $this->options)) {
            $this->options['version'] = 1;
        }

        $this->root = $this->host . '/api/';
    }

    public function clearCache(): void
    {
        $this->cache->remove(self::CACHE_STATUS);
        $this->cache->remove(self::CACHE_ENDPOINTS);
    }

    public function status(): string
    {
        $cached = $this->cache->load(self::CACHE_STATUS);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $result = $this->request('status', 'GET', ['version' => false]);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        $status = strtolower((string)($result['status'] ?? ''));
        if ($status !== '') {
            $this->cache->save($status, self::CACHE_STATUS, [], 300);
        }

        return $status;
    }

    public function isOnline(): bool
    {
        return $this->status() === 'online';
    }

    public function listAutomations(): array
    {
        return $this->request('rest/v1/automation', 'GET', ['version' => false]);
    }

    public function listConnections(): array
    {
        return $this->request('rest/v1/connection', 'GET', ['version' => false]);
    }

    public function listEndpoints(): array
    {
        $cached = $this->cache->load(self::CACHE_ENDPOINTS);
        if (is_string($cached) && $cached !== '') {
            try {
                $decoded = $this->serializer->unserialize($cached);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable) {
                // Ignore stale cache payloads.
            }
        }

        $result = $this->request('endpoint', 'GET', ['version' => false]);
        if (is_array($result) && $result !== []) {
            $this->cache->save($this->serializer->serialize($result), self::CACHE_ENDPOINTS, [], 300);
        }

        return $result;
    }

    public function executeEndpoint(string $endpoint): array
    {
        return $this->triggerEndpoint($endpoint, [], 'execute');
    }

    public function triggerEndpoint(string $endpoint, array $payload = [], string $action = 'execute'): array
    {
        $endpoint = trim($endpoint, '/');
        $action = trim($action, '/');

        if ($endpoint === '' || $action === '') {
            return ['success' => false, 'error' => 'Invalid endpoint action request.'];
        }

        try {
            $result = $this->request(
                'endpoint/' . $endpoint . '/' . $action,
                'POST',
                [
                    'version' => false,
                    'body' => $this->serializer->serialize($payload),
                    'headers' => ['Content-Type' => 'application/json'],
                ]
            );

            return is_array($result) ? $result : ['success' => true, 'data' => $result];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function request(string $endpoint, string $method = 'GET', array $options = []): array
    {
        $options = array_merge($this->options, $options);
        $headers = (array)($options['headers'] ?? []);

        $authHeader = trim((string)($options['auth_header'] ?? ''));
        if ($authHeader === '') {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        } else {
            $headers[$authHeader] = $this->token;
        }

        unset($options['auth_header']);

        $url = $this->root;
        if (array_key_exists('version', $options) && $options['version'] !== false) {
            $url .= 'v' . (int)$options['version'] . '/';
        }
        $url .= ltrim($endpoint, '/');

        if ($headers !== []) {
            $this->curl->setHeaders($headers);
        }

        $query = (array)($options['query'] ?? []);
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $body = $options['body'] ?? null;

        try {
            switch (strtoupper($method)) {
                case 'POST':
                    $this->curl->post($url, $body ?? '');
                    break;
                case 'PUT':
                    $this->curl->put($url, $body ?? '');
                    break;
                case 'DELETE':
                    $this->curl->delete($url);
                    break;
                default:
                    $this->curl->get($url);
            }
        } catch (\Throwable $e) {
            $this->clearCache();
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        $status = (int)$this->curl->getStatus();
        $content = (string)$this->curl->getBody();

        if ($status !== 200) {
            $this->clearCache();
            $message = 'HTTP ' . $status;
            $decoded = $this->decodeBody($content);
            if (is_array($decoded) && !empty($decoded['message'])) {
                $message .= ': ' . (string)$decoded['message'];
            }

            throw new \RuntimeException($message . ' [' . $url . ']');
        }

        $decoded = $this->decodeBody($content);
        return is_array($decoded) ? $decoded : [];
    }

    private function decodeBody(string $content): array|string
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        try {
            $decoded = $this->serializer->unserialize($content);
            return is_array($decoded) ? $decoded : (string)$content;
        } catch (\Throwable) {
            return $content;
        }
    }
}
