<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Service;

use Magento\Framework\App\ObjectManager;
use Psr\Log\LoggerInterface;

class EndpointDispatcherService
{
    private DispatchLogService $dispatchLogService;

    public function __construct(
        private readonly ClientService $clientService,
        private readonly LoggerInterface $logger,
        ?DispatchLogService $dispatchLogService = null
    ) {
        // Keep this constructor backward-compatible with cached/generated DI metadata
        // that may still pass only (ClientService, LoggerInterface).
        $this->dispatchLogService = $dispatchLogService
            ?? ObjectManager::getInstance()->get(DispatchLogService::class);
    }

    public function triggerEndpoints(array $endpoints, array $payload = [], array $meta = []): array
    {
        $client = $this->clientService->getClient();
        if (!$client) {
            return [];
        }

        $source = (string)($meta['source'] ?? 'unknown');
        $trigger = (string)($meta['trigger'] ?? '');

        $results = [];
        foreach (array_values(array_filter(array_map('strval', $endpoints))) as $endpoint) {
            $result = $client->triggerEndpoint($endpoint, $payload);
            $results[$endpoint] = $result;

            $this->dispatchLogService->add($source, $trigger, $endpoint, $payload, (array)$result, 'dispatched');

            $success = (bool)($result['success'] ?? true);
            $context = [
                'source' => $source,
                'trigger' => $trigger,
                'endpoint' => $endpoint,
                'payload_size' => strlen(json_encode($payload) ?: ''),
                'error' => (string)($result['error'] ?? ''),
            ];

            if ($success) {
                $this->logger->info('SyncEngine endpoint dispatched', $context);
            } else {
                $this->logger->warning('SyncEngine endpoint dispatch failed', $context);
            }
        }

        return $results;
    }
}
