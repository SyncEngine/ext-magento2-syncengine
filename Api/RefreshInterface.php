<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Api;

interface RefreshInterface
{
    /**
     * Clear the trigger endpoint map cache.
     *
     * @return mixed[]
     */
    public function refresh(): array;
}
