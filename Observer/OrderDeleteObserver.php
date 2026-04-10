<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SyncEngine\Connector\Helper\Data;
use SyncEngine\Connector\Service\MagentoPlatformService;
use SyncEngine\Connector\Service\MagentoRestPayloadService;

class OrderDeleteObserver implements ObserverInterface
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

        $payload = [
            'id' => $id,
            'event' => 'magento_deleted_order',
            'data' => $this->payloadService->normalizeValue($order->getData()),
            'request' => ['id' => $id],
        ];

        $this->platformService->triggerEndpoints(MagentoPlatformService::TRIGGER_DELETED_ORDER, $payload, ['event' => 'sales_order_delete_before']);
    }
}
