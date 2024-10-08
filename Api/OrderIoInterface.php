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

interface OrderIoInterface
{
    /**
     * Filter Orders
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
        int $limit = 100
    ): array;

    /**
     * Get Order by ID
     *
     * @param int $orderId
     * @return array
     */
    public function getById(int $orderId): array;

    /**
     * Get Order by increment ID
     *
     * @param string $incrementId
     * @return array
     */
    public function getByIncrementId(string $incrementId): array;

    /**
     * Get Payment Methods
     *
     * @return array
     */
    public function getPaymentMethods(): array;

    /**
     * Get Shipping Methods
     *
     * @return array
     */
    public function getShippingMethods(): array;

    /**
     * Get Shipping Carriers
     *
     * @return array
     */
    public function getShippingCarriers(): array;
}
