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

interface OrderManagementInterface
{
    /**
     * Create or update Order
     *
     * @param int $store
     * @param string[] $orderInfo
     * @param string[] $paymentInfo
     * @param string[] $shippingInfo
     * @param string[] $billingInfo
     * @param mixed $itemInfo
     * @param mixed $statusHistories
     * @param mixed $shipmentInfo
     * @param mixed $refundInfo
     * @return array
     */
    public function import(
        int $store,
        array $orderInfo,
        array $paymentInfo,
        array $shippingInfo,
        array $billingInfo,
        mixed $itemInfo,
        mixed $statusHistories = [],
        mixed $shipmentInfo = [],
        mixed $refundInfo = []
    ): array;

    /**
     * Cancel Order
     *
     * @param string $orderId
     * @return array
     */
    public function cancel(string $orderId): array;
}
