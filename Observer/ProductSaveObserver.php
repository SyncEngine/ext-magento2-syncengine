<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SyncEngine\Connector\Helper\Data;
use SyncEngine\Connector\Service\MagentoPlatformService;
use SyncEngine\Connector\Service\MagentoRestPayloadService;

class ProductSaveObserver implements ObserverInterface
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

        $isNew = !$product->getOrigData('entity_id');
        $trigger = $isNew ? MagentoPlatformService::TRIGGER_NEW_PRODUCT : MagentoPlatformService::TRIGGER_UPDATED_PRODUCT;

        $payload = [
            'id' => $id,
            'event' => $isNew ? 'magento_new_product' : 'magento_updated_product',
            'data' => $this->payloadService->getProductData($id),
            'request' => ['id' => $id],
        ];

        $this->platformService->triggerEndpoints($trigger, $payload, ['event' => 'catalog_product_save_after']);
    }
}
