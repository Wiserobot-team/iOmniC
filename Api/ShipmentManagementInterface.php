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

namespace WiseRobot\Io\Api;

interface ShipmentManagementInterface
{
    /**
     * Filter Shipments
     *
     * @param int $store
     * @param string $filter
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getList(
        int $store,
        string $filter = "",
        int $page = 1,
        int $limit = 1000
    ): array;

    /**
     * Get Shipment by ID
     *
     * @param int $shipmentId
     * @return array
     */
    public function getById(int $shipmentId): array;

    /**
     * Get Shipment by increment ID
     *
     * @param string $incrementId
     * @return array
     */
    public function getByIncrementId(string $incrementId): array;

    /**
     * Create or update Shipment
     *
     * @param string $orderId
     * @param mixed $shipmentInfo
     * @return array
     */
    public function import(
        string $orderId,
        mixed $shipmentInfo
    ): array;
}
