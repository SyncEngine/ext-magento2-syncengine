<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Service;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;

class RefreshTrustService
{
    private const CACHE_KEY = 'syncengine_known_connection_refs';
    private const CACHE_TTL = 604800; // 7 days

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $serializer,
    ) {}

    /**
     * @param string[] $refs
     */
    public function rememberConnectionRefs(array $refs): void
    {
        $refs = $this->normalizeRefs($refs);
        if ($refs === []) {
            return;
        }

        $existing = $this->getConnectionRefs();
        $merged = array_values(array_unique(array_merge($existing, $refs)));
        sort($merged);

        if ($merged !== $existing) {
            $this->cache->save($this->serializer->serialize($merged), self::CACHE_KEY, [], self::CACHE_TTL);
        }
    }

    public function isTrustedConnectionRef(string $ref): bool
    {
        $ref = $this->normalizeRef($ref);
        if ($ref === '') {
            return false;
        }

        return in_array($ref, $this->getConnectionRefs(), true);
    }

    /**
     * @return string[]
     */
    private function getConnectionRefs(): array
    {
        $cached = $this->cache->load(self::CACHE_KEY);
        if (!is_string($cached) || $cached === '') {
            return [];
        }

        try {
            $refs = $this->serializer->unserialize($cached);
            return is_array($refs) ? $this->normalizeRefs($refs) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param mixed[] $refs
     * @return string[]
     */
    private function normalizeRefs(array $refs): array
    {
        return array_values(array_filter(array_map([$this, 'normalizeRef'], $refs)));
    }

    private function normalizeRef(mixed $ref): string
    {
        return strtolower(trim((string) $ref));
    }
}
