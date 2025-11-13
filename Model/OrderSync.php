<?php

/**
 * WISEROBOT INDUSTRIES SDN. BHD. **NOTICE OF LICENSE**
 * This source file is subject to the EULA that is bundled with this package in the file LICENSE.pdf
 * It is also available through the world-wide-web at this URL: http://wiserobot.com/mage_extension_license.pdf
 * =================================================================
 * This package is designed for all versions of Magento
 * =================================================================
 * Copyright (c) 2019 WISEROBOT INDUSTRIES SDN. BHD. (http://www.wiserobot.com)
 * License http://wiserobot.com/mage_extension_license.pdf
 */

declare(strict_types=1);

namespace WiseRobot\Io\Model;

use Magento\Framework\Webapi\Exception as WebapiException;
use WiseRobot\Io\Model\IoOrderFactory;

class OrderSync implements \WiseRobot\Io\Api\OrderSyncInterface
{
    /**
     * @var array
     */
    public array $results = [];
    /**
     * @var IoOrderFactory
     */
    public $ioOrderFactory;

    /**
     * @param IoOrderFactory $ioOrderFactory
     */
    public function __construct(
        IoOrderFactory $ioOrderFactory
    ) {
        $this->ioOrderFactory = $ioOrderFactory;
    }

    /**
     * Get Order Sync by ID
     *
     * @param int $id
     * @return array
     */
    public function getById(int $id): array
    {
        return $this->getOrderSync($id, 'id');
    }

    /**
     * Get Order Sync by increment ID
     *
     * @param string $incrementId
     * @return array
     */
    public function getByIncrementId(string $incrementId): array
    {
        return $this->getOrderSync($incrementId);
    }

    /**
     * Get Order Sync
     *
     * @param int|string $id
     * @param string $typeId
     * @return array
     */
    public function getOrderSync(int|string $id, string $typeId = 'incrementId'): array
    {
        $typeId === "id"
            ? $orderSync = $this->ioOrderFactory->create()->load($id)
            : $orderSync = $this->ioOrderFactory->create()->load($id, "order_increment_id");
        if (!$orderSync || !$orderSync->getId()) {
            return [];
        }
        $orderSyncData = $this->getOrderSyncInfo($orderSync);
        return !empty($orderSyncData) ? [$id => $orderSyncData] : [];
    }

    /**
     * Get Order Sync Info
     *
     * @param \WiseRobot\Io\Model\IoOrder $orderSync
     * @return array
     */
    public function getOrderSyncInfo(
        \WiseRobot\Io\Model\IoOrder $orderSync
    ): array {
        return [
            "id" => $orderSync->getData('id'),
            "order_increment_id" => $orderSync->getData('order_increment_id'),
            "io_order_id" => $orderSync->getData('io_order_id'),
            "marketplace" => $orderSync->getData("marketplace"),
            "transaction_id" => $orderSync->getData("transaction_id"),
        ];
    }

    /**
     * Delete Order Sync by ID
     *
     * @param int $id
     * @return array
     */
    public function deleteById(int $id): array
    {
        return $this->deleteOrderSync($id, 'id');
    }

    /**
     * Delete Order Sync by increment ID
     *
     * @param string $incrementId
     * @return array
     */
    public function deleteByIncrementId(string $incrementId): array
    {
        return $this->deleteOrderSync($incrementId);
    }

    /**
     * Delete Order Sync
     *
     * @param int|string $id
     * @param string $typeId
     * @return array
     */
    public function deleteOrderSync(int|string $id, string $typeId = 'incrementId'): array
    {
        $typeId === "id"
            ? $orderSync = $this->ioOrderFactory->create()->load($id)
            : $orderSync = $this->ioOrderFactory->create()->load($id, "order_increment_id");
        if (!$orderSync || !$orderSync->getId()) {
            $message = "Requested order sync {$typeId}: {$id} doesn't exist";
            $this->handleValidationError($message);
        }
        try {
            $orderSync->delete();
            $message = "Order sync {$typeId}: {$id} was successfully deleted";
            $this->results["response"]["data"]["success"][] = $message;
        } catch (\Exception $e) {
            $message = "Error deleting order sync {$typeId}: {$id} - {$e->getMessage()}";
            $this->handleValidationError($message);
        }
        return $this->results;
    }

    /**
     * Handles validation errors
     *
     * @param string $message
     * @return void
     */
    public function handleValidationError(string $message): void
    {
        $errorMess = "Error deleting order sync";
        $this->results["response"]["data"]["error"][] = $message;
        throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
    }
}
