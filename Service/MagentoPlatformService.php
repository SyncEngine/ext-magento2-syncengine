<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Service;

use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\StoreManagerInterface;
use SyncEngine\Connector\Api\Client;
use SyncEngine\Connector\Helper\Data;

class MagentoPlatformService extends AbstractPlatformService
{
    public const TRANSIENT_TRIGGER_ENDPOINT_MAP = 'syncengine_magento_trigger_endpoint_map';
    public const M2_WEBSERVICE_CLASS = 'SyncEngine/Magento2RestV1:Magento2TokenOAuth';

    public const TRIGGER_NEW_PRODUCT = 'new_product';
    public const TRIGGER_UPDATED_PRODUCT = 'updated_product';
    public const TRIGGER_DELETED_PRODUCT = 'deleted_product';

    public const TRIGGER_NEW_CUSTOMER = 'new_customer';
    public const TRIGGER_UPDATED_CUSTOMER = 'updated_customer';
    public const TRIGGER_DELETED_CUSTOMER = 'deleted_customer';

    public const TRIGGER_NEW_ORDER = 'new_order';
    public const TRIGGER_UPDATED_ORDER = 'updated_order';
    public const TRIGGER_DELETED_ORDER = 'deleted_order';

    public function __construct(
        ClientService $clientService,
        EndpointDispatcherService $dispatcher,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        \Psr\Log\LoggerInterface $logger,
        private readonly StoreManagerInterface $storeManager,
        private readonly Data $dataHelper,
        ?DispatchLogService $dispatchLogService = null
    ) {
        parent::__construct($clientService, $dispatcher, $cache, $serializer, $logger, $dispatchLogService);
    }

    protected function getTriggerEvents(): array
    {
        return [
            self::TRIGGER_NEW_PRODUCT,
            self::TRIGGER_UPDATED_PRODUCT,
            self::TRIGGER_DELETED_PRODUCT,
            self::TRIGGER_NEW_CUSTOMER,
            self::TRIGGER_UPDATED_CUSTOMER,
            self::TRIGGER_DELETED_CUSTOMER,
            self::TRIGGER_NEW_ORDER,
            self::TRIGGER_UPDATED_ORDER,
            self::TRIGGER_DELETED_ORDER,
        ];
    }

    protected function getBlueprintClassMap(): array
    {
        return [
            'SyncEngine/Magento2RestV1:NewProduct' => self::TRIGGER_NEW_PRODUCT,
            'SyncEngine/Magento2RestV1:UpdatedProduct' => self::TRIGGER_UPDATED_PRODUCT,
            'SyncEngine/Magento2RestV1:DeletedProduct' => self::TRIGGER_DELETED_PRODUCT,
            'SyncEngine/Magento2RestV1:NewCustomer' => self::TRIGGER_NEW_CUSTOMER,
            'SyncEngine/Magento2RestV1:UpdatedCustomer' => self::TRIGGER_UPDATED_CUSTOMER,
            'SyncEngine/Magento2RestV1:DeletedCustomer' => self::TRIGGER_DELETED_CUSTOMER,
            'SyncEngine/Magento2RestV1:NewOrder' => self::TRIGGER_NEW_ORDER,
            'SyncEngine/Magento2RestV1:UpdatedOrder' => self::TRIGGER_UPDATED_ORDER,
            'SyncEngine/Magento2RestV1:DeletedOrder' => self::TRIGGER_DELETED_ORDER,
        ];
    }

    protected function getLocalConnectionIds(Client $client): array
    {
        $connections = $client->listConnections();
        if (!is_array($connections)) {
            return [];
        }

        $localHosts = $this->getLocalStoreHosts();
        if ($localHosts === []) {
            return [];
        }

        $ids = [];
        $refs = [];
        foreach ($connections as $connection) {
            if (!is_array($connection)) {
                continue;
            }

            $id = (int)($connection['id'] ?? 0);
            $config = (array)($connection['config'] ?? []);
            $webservice = (array)($config['webservice'] ?? []);
            $class = (string)($webservice['_class'] ?? '');
            if ($class !== self::M2_WEBSERVICE_CLASS) {
                continue;
            }

            $host = $this->normalizeStoreHost((string)($webservice['host'] ?? ''));
            if ($id > 0 && $host !== '' && in_array($host, $localHosts, true)) {
                $ids[] = $id;
                $ref = trim((string)($connection['ref'] ?? ''));
                if ($ref !== '') {
                    $refs[] = $ref;
                }
            }
        }

        if ($refs !== []) {
            ObjectManager::getInstance()->get(RefreshTrustService::class)->rememberConnectionRefs($refs);
        }

        return array_values(array_unique($ids));
    }

    protected function getCacheKey(): string
    {
        return self::TRANSIENT_TRIGGER_ENDPOINT_MAP;
    }

    protected function getSource(): string
    {
        return 'magento2';
    }

    protected function getCacheTtl(): int
    {
        return max(0, $this->dataHelper->getTriggerMapTtl());
    }

    private function getLocalStoreHosts(): array
    {
        $hosts = [];

        try {
            $store = $this->storeManager->getStore();
            $hosts[] = $this->normalizeStoreHost((string)$store->getBaseUrl());
            $hosts[] = $this->normalizeStoreHost((string)$store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB, true));
        } catch (\Throwable) {
            // Ignore and return known hosts only.
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    private function normalizeStoreHost(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $host = strtolower((string)$parts['host']);
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        $path = trim((string)($parts['path'] ?? ''), '/');

        $path = preg_replace('#/rest(?:/[^/]+)?(?:/async/bulk)?/V1$#i', '', $path ?? '');
        $path = trim((string)$path, '/');

        return $host . $port . ($path !== '' ? '/' . $path : '');
    }
}
