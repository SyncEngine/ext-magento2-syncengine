<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Controller\Adminhtml\Debug;

use Magento\Backend\App\Action;
use SyncEngine\Connector\Service\DispatchLogService;

class ClearLog extends Action
{
    public const ADMIN_RESOURCE = 'SyncEngine_Connector::trigger_debug';

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly DispatchLogService $dispatchLogService
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $this->dispatchLogService->clearLog();

        $this->messageManager->addSuccessMessage(__('Dispatch log has been cleared.'));
        return $this->_redirect('syncengine_connector/debug/index');
    }
}
