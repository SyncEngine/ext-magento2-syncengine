<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Controller\Adminhtml\Debug;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'SyncEngine_Connector::trigger_debug';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('SyncEngine_Connector::trigger_debug');
        $resultPage->getConfig()->getTitle()->prepend(__('SyncEngine Trigger Debug'));

        return $resultPage;
    }
}
