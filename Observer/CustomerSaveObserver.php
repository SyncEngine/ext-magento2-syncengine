<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SyncEngine\Connector\Helper\Data;
use SyncEngine\Connector\Service\MagentoPlatformService;
use SyncEngine\Connector\Service\MagentoRestPayloadService;

class CustomerSaveObserver implements ObserverInterface
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

        $event = $observer->getEvent();
        $customer = $event->getCustomerDataObject() ?: $event->getCustomer();
        if (!$customer) {
            return;
        }

        $id = (int)$customer->getId();
        if ($id <= 0) {
            return;
        }

        $isNew = !$event->getOrigCustomerDataObject();
        $trigger = $isNew ? MagentoPlatformService::TRIGGER_NEW_CUSTOMER : MagentoPlatformService::TRIGGER_UPDATED_CUSTOMER;

        $payload = [
            'id' => $id,
            'event' => $isNew ? 'magento_new_customer' : 'magento_updated_customer',
            'data' => $this->payloadService->getCustomerData($id),
            'request' => ['id' => $id],
        ];

        $this->platformService->triggerEndpoints($trigger, $payload, ['event' => 'customer_save_after_data_object']);
    }
}
