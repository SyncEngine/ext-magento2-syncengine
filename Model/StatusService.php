<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Model;

use SyncEngine\Connector\Api\StatusInterface;

class StatusService implements StatusInterface
{
    public function status(): array
    {
        return [
            'success' => true,
            'status'  => 'active',
        ];
    }
}
