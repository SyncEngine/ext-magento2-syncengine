<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SyncEngine\Connector\Helper\Data;
use SyncEngine\Connector\Service\MagentoPlatformService;
use SyncEngine\Connector\Service\MagentoRestPayloadService;

class ProductDeleteObserver implements ObserverInterface
{
    public function __construct(
        private readonly MagentoPlatformService $platformService,
        private readonly MagentoRestPayloadService $payloadService,
        private readonly Data $dataHelper
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->dataHelper->isTriggerDispatchEnabled()) {
            return;
        }

        $product = $observer->getEvent()->getProduct();
        if (!$product) {
            return;
        }

        $id = (int)$product->getId();
        if ($id <= 0) {
            return;
        }

        $payload = [
            'id' => $id,
            'event' => 'magento_deleted_product',
            'data' => $this->payloadService->normalizeValue($product->getData()),
            'request' => ['id' => $id],
        ];

        $this->platformService->triggerEndpoints(MagentoPlatformService::TRIGGER_DELETED_PRODUCT, $payload, ['event' => 'catalog_product_delete_before']);
    }
}
