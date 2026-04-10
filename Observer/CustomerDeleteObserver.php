<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SyncEngine\Connector\Helper\Data;
use SyncEngine\Connector\Service\MagentoPlatformService;
use SyncEngine\Connector\Service\MagentoRestPayloadService;

class CustomerDeleteObserver implements ObserverInterface
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

        $customer = $observer->getEvent()->getCustomerDataObject() ?: $observer->getEvent()->getCustomer();
        if (!$customer) {
            return;
        }

        $id = (int)$customer->getId();
        if ($id <= 0) {
            return;
        }

        $payload = [
            'id' => $id,
            'event' => 'magento_deleted_customer',
            'data' => $this->payloadService->normalizeValue(method_exists($customer, '__toArray') ? $customer->__toArray() : []),
            'request' => ['id' => $id],
        ];

        $this->platformService->triggerEndpoints(MagentoPlatformService::TRIGGER_DELETED_CUSTOMER, $payload, ['event' => 'customer_delete_after_data_object']);
    }
}
