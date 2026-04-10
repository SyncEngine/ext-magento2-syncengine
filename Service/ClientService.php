<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Service;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use SyncEngine\Connector\Api\Client;
use SyncEngine\Connector\Helper\Data;

class ClientService
{
    public function __construct(
        private readonly Data $dataHelper,
        private readonly CurlFactory $curlFactory,
        private readonly CacheInterface $cache,
        private readonly Json $serializer
    ) {
    }

    public function getClient(): ?Client
    {
        $host = trim($this->dataHelper->getApiHost());
        $token = trim($this->dataHelper->getApiToken());

        if ($host === '' || $token === '') {
            return null;
        }

        return new Client(
            $host,
            $token,
            [
                'version' => $this->dataHelper->getApiVersion(),
                'auth_header' => $this->dataHelper->getApiAuthHeader(),
            ],
            $this->curlFactory->create(),
            $this->cache,
            $this->serializer
        );
    }
}
