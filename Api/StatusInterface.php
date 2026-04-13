<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Api;

interface StatusInterface
{
    /**
     * Return connector status.
     *
     * @return mixed[]
     */
    public function status(): array;
}
