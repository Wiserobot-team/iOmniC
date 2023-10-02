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

namespace WiseRobot\Io\Api;

interface ShipmentImportInterface
{
    /**
     * Create or update Shipment
     *
     * @param string $orderId
     * @param mixed $shipmentInfo
     * @return array
     */
    public function import(
        $orderId,
        $shipmentInfo
    );
}
