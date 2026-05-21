<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Controller\Adminhtml\Debug;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use SyncEngine\Connector\Service\ClientService;

class GetEndpointStatus extends Action
{
    public const ADMIN_RESOURCE = 'SyncEngine_Connector::connector';

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ClientService $clientService,
        private readonly Validator $formKeyValidator
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        // Verify request is AJAX
        if (!$this->getRequest()->isAjax()) {
            return $resultJson->setData([
                'success' => false,
                'message' => 'Invalid request method.',
            ]);
        }

        // Validate form key (CSRF token)
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $resultJson->setData([
                'success' => false,
                'message' => 'Security check failed.',
            ]);
        }

        // Get endpoint parameter
        $endpoint = trim((string)$this->getRequest()->getParam('endpoint', ''), '/');

        if ($endpoint === '') {
            return $resultJson->setData([
                'success' => false,
                'message' => 'Endpoint not specified.',
            ]);
        }

        // Get API client
        $client = $this->clientService->getClient();
        if (!$client) {
            return $resultJson->setData([
                'success' => false,
                'message' => 'API settings not configured.',
            ]);
        }

        // Fetch endpoint status
        $status = $client->getEndpointStatus($endpoint, true);

        if (!empty($status['success'])) {
            // Extract only the fields we need to avoid double-wrapping
            return $resultJson->setData([
                'success' => true,
                'data' => [
                    'status' => $status['status'] ?? 'unknown',
                    'message' => $status['message'] ?? '',
                    'running' => $status['running'] ?? [],
                    'scheduled' => $status['scheduled'] ?? [],
                    'queued' => $status['queued'] ?? [],
                ],
            ]);
        }

        return $resultJson->setData([
            'success' => false,
            'message' => $status['error'] ?? 'Failed to load status.',
        ]);
    }
}
