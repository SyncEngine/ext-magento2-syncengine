<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Controller\Adminhtml\Debug;

use Magento\Backend\App\Action;
use SyncEngine\Connector\Service\MagentoPlatformService;

class RefreshMap extends Action
{
    public const ADMIN_RESOURCE = 'SyncEngine_Connector::trigger_debug';

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly MagentoPlatformService $platformService
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $this->platformService->clearTriggerEndpointMapCache();
        $this->platformService->getTriggerEndpointMap(true);

        $this->messageManager->addSuccessMessage(__('Trigger endpoint map has been refreshed.'));
        return $this->_redirect('syncengine_connector/debug/index');
    }
}
