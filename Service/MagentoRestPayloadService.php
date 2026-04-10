<?php

declare(strict_types=1);

namespace SyncEngine\Connector\Service;

use Magento\Catalog\Model\ProductFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Sales\Model\OrderFactory;

class MagentoRestPayloadService
{
    public function __construct(
        private readonly ProductFactory $productFactory,
        private readonly CustomerFactory $customerFactory,
        private readonly OrderFactory $orderFactory
    ) {
    }

    public function getProductData(int $id): array
    {
        if ($id <= 0) {
            return [];
        }

        try {
            return $this->normalizeValue($this->productFactory->create()->load($id)->getData());
        } catch (\Throwable) {
            return [];
        }
    }

    public function getCustomerData(int $id): array
    {
        if ($id <= 0) {
            return [];
        }

        try {
            return $this->normalizeValue($this->customerFactory->create()->load($id)->getData());
        } catch (\Throwable) {
            return [];
        }
    }

    public function getOrderData(int $id): array
    {
        if ($id <= 0) {
            return [];
        }

        try {
            return $this->normalizeValue($this->orderFactory->create()->load($id)->getData());
        } catch (\Throwable) {
            return [];
        }
    }

    public function normalizeValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth >= 4) {
            if (is_scalar($value) || $value === null) {
                return $value;
            }

            return is_object($value) ? get_class($value) : gettype($value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item, $depth + 1);
            }

            return $normalized;
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $this->normalizeValue($value->toArray(), $depth + 1);
            }
            if (method_exists($value, 'getData')) {
                return $this->normalizeValue($value->getData(), $depth + 1);
            }

            return $this->normalizeValue(get_object_vars($value), $depth + 1);
        }

        return gettype($value);
    }
}
