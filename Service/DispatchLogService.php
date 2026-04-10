<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Service;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;

class DispatchLogService
{
    private const CACHE_KEY = 'syncengine_dispatch_log';
    private const MAX_LOG_ITEMS = 200;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $serializer
    ) {
    }

    public function clearLog(): void
    {
        $this->cache->remove(self::CACHE_KEY);
    }

    public function getLog(): array
    {
        $cached = $this->cache->load(self::CACHE_KEY);
        if (!is_string($cached) || $cached === '') {
            return [];
        }

        try {
            $decoded = $this->serializer->unserialize($cached);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function getLatest(int $limit = 25): array
    {
        $log = $this->getLog();
        return array_slice($log, 0, max(1, $limit));
    }

    public function add(string $source, string $trigger, string $endpoint, array $payload, array $result = [], string $status = 'dispatched'): void
    {
        $entry = [
            'timestamp' => time(),
            'source' => $source,
            'trigger' => $trigger,
            'endpoint' => $endpoint,
            'status' => $status,
            'payload_size' => strlen(json_encode($payload) ?: ''),
            'success' => (bool)($result['success'] ?? true),
            'error' => (string)($result['error'] ?? ''),
        ];

        $log = $this->getLog();
        array_unshift($log, $entry);
        $log = array_slice($log, 0, self::MAX_LOG_ITEMS);

        $this->cache->save($this->serializer->serialize($log), self::CACHE_KEY, [], 604800);
    }
}
