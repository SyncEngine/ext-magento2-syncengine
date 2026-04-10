<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use SyncEngine\Connector\Service\ClientService;
use SyncEngine\Connector\Service\DispatchLogService;
use SyncEngine\Connector\Service\MagentoPlatformService;

class DebugPage extends Template
{
    protected $_template = 'SyncEngine_Connector::debug/page.phtml';

    public function __construct(
        Context $context,
        private readonly ClientService $clientService,
        private readonly MagentoPlatformService $platformService,
        private readonly DispatchLogService $dispatchLogService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getStatus(): string
    {
        $client = $this->clientService->getClient();
        if (!$client) {
            return 'not configured';
        }

        return (string)$client->status();
    }

    public function getTriggerMap(): array
    {
        return $this->platformService->getTriggerEndpointMap();
    }

    public function getDispatchLog(): array
    {
        return $this->dispatchLogService->getLatest(50);
    }

    public function getRefreshMapUrl(): string
    {
        return $this->getUrl('syncengine_connector/debug/refreshMap');
    }

    public function getClearLogUrl(): string
    {
        return $this->getUrl('syncengine_connector/debug/clearLog');
    }
}
