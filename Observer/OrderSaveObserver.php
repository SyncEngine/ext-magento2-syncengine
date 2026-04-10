<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SyncEngine\Connector\Helper\Data;
use SyncEngine\Connector\Service\MagentoPlatformService;
use SyncEngine\Connector\Service\MagentoRestPayloadService;

class OrderSaveObserver implements ObserverInterface
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

        $order = $observer->getEvent()->getOrder();
        if (!$order) {
            return;
        }

        $id = (int)$order->getId();
        if ($id <= 0) {
            return;
        }

        $isNew = !$order->getOrigData('entity_id');
        $trigger = $isNew ? MagentoPlatformService::TRIGGER_NEW_ORDER : MagentoPlatformService::TRIGGER_UPDATED_ORDER;

        $payload = [
            'id' => $id,
            'event' => $isNew ? 'magento_new_order' : 'magento_updated_order',
            'data' => $this->payloadService->getOrderData($id),
            'request' => ['id' => $id],
        ];

        $this->platformService->triggerEndpoints($trigger, $payload, ['event' => 'sales_order_save_after']);
    }
}
