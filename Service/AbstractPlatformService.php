<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Service;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

abstract class AbstractPlatformService
{
    private DispatchLogService $dispatchLogService;

    public function __construct(
        private readonly ClientService $clientService,
        private readonly EndpointDispatcherService $dispatcher,
        private readonly CacheInterface $cache,
        private readonly Json $serializer,
        private readonly LoggerInterface $logger,
        ?DispatchLogService $dispatchLogService = null
    ) {
        // Keep constructor compatible with stale generated DI metadata.
        $this->dispatchLogService = $dispatchLogService
            ?? ObjectManager::getInstance()->get(DispatchLogService::class);
    }

    public function getTriggerEndpointMap(bool $refresh = false): array
    {
        if (!$refresh) {
            $cached = $this->cache->load($this->getCacheKey());
            if (is_string($cached) && $cached !== '') {
                try {
                    $decoded = $this->serializer->unserialize($cached);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                } catch (\Throwable) {
                    // Ignore invalid cache and rebuild map.
                }
            }
        }

        $map = [];
        foreach ($this->getTriggerEvents() as $event) {
            $map[$event] = [];
        }

        $client = $this->clientService->getClient();
        if (!$client) {
            return $map;
        }

        try {
            $automations = $client->listAutomations();
            $localConnectionIds = $this->getLocalConnectionIds($client);
            $classMap = $this->getBlueprintClassMap();

            if ($localConnectionIds === []) {
                return $map;
            }

            foreach ($automations as $automation) {
                if (!is_array($automation)) {
                    continue;
                }

                $endpoint = trim((string)($automation['endpoint'] ?? ''));
                if ($endpoint === '') {
                    continue;
                }

                $blueprint = (array)($automation['config']['_blueprint'] ?? []);
                $blueprintClass = (string)($blueprint['_class'] ?? '');
                $blueprintConnection = isset($blueprint['connection']) ? (int)$blueprint['connection'] : 0;

                if (!isset($classMap[$blueprintClass]) || $blueprintConnection <= 0) {
                    continue;
                }

                if (!in_array($blueprintConnection, $localConnectionIds, true)) {
                    continue;
                }

                $map[$classMap[$blueprintClass]][] = $endpoint;
            }

            foreach ($map as $event => $endpoints) {
                $map[$event] = array_values(array_unique($endpoints));
            }

            $ttl = max(0, $this->getCacheTtl());
            if ($ttl > 0) {
                $this->cache->save($this->serializer->serialize($map), $this->getCacheKey(), [], $ttl);
            } else {
                $this->cache->remove($this->getCacheKey());
            }
        } catch (\Throwable $e) {
            $this->logger->error('SyncEngine trigger map build failed', [
                'source' => $this->getSource(),
                'error' => $e->getMessage(),
            ]);
        }

        return $map;
    }

    public function getEndpointsForTrigger(string $trigger): array
    {
        $map = $this->getTriggerEndpointMap();
        return (array)($map[$trigger] ?? []);
    }

    public function triggerEndpoints(string $trigger, array $payload = [], array $context = []): array
    {
        $endpoints = $this->getEndpointsForTrigger($trigger);
        if ($endpoints === []) {
            $this->dispatchLogService->add(
                $this->getSource(),
                $trigger,
                '',
                $payload,
                ['success' => false, 'error' => 'No mapped endpoints for trigger.'],
                'skipped'
            );

            return [];
        }

        return $this->dispatcher->triggerEndpoints(
            $endpoints,
            $payload,
            [
                'source' => $this->getSource(),
                'trigger' => $trigger,
                'context' => $context,
            ]
        );
    }

    public function clearTriggerEndpointMapCache(): void
    {
        $this->cache->remove($this->getCacheKey());
    }

    abstract protected function getTriggerEvents(): array;

    abstract protected function getBlueprintClassMap(): array;

    abstract protected function getLocalConnectionIds(\SyncEngine\Connector\Api\Client $client): array;

    abstract protected function getCacheKey(): string;

    abstract protected function getSource(): string;

    protected function getCacheTtl(): int
    {
        return 300;
    }
}
