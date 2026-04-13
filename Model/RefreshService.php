<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use SyncEngine\Connector\Api\RefreshInterface;
use SyncEngine\Connector\Service\MagentoPlatformService;
use SyncEngine\Connector\Service\RefreshTrustService;

class RefreshService implements RefreshInterface
{
    private const THROTTLE_CACHE_KEY = 'syncengine_refresh_throttle';
    private const THROTTLE_SECONDS = 10;
    private const TRUSTED_THROTTLE_SECONDS = 1;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly RequestInterface $request,
        private readonly MagentoPlatformService $platformService,
        private readonly RefreshTrustService $trustService,
    ) {}

    public function refresh(): array
    {
        $connectionRef = trim((string) $this->request->getHeader('X-SyncEngine-Connection'));
        $trustedRefresh = $connectionRef !== '' && $this->trustService->isTrustedConnectionRef($connectionRef);

        $throttleKey = self::THROTTLE_CACHE_KEY . ($trustedRefresh ? '_trusted' : '');
        $throttleTtl = $trustedRefresh ? self::TRUSTED_THROTTLE_SECONDS : self::THROTTLE_SECONDS;

        if ($throttleTtl > 0 && $this->cache->load($throttleKey) !== false) {
            return [
                'success'   => true,
                'refreshed' => false,
                'reason'    => 'throttled',
                'trusted'   => $trustedRefresh,
            ];
        }

        if ($throttleTtl > 0) {
            $this->cache->save('1', $throttleKey, [], $throttleTtl);
        }

        $this->platformService->clearTriggerEndpointMapCache();

        return [
            'success'   => true,
            'refreshed' => true,
            'trusted'   => $trustedRefresh,
            'timestamp' => time(),
        ];
    }
}
