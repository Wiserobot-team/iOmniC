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

use Magento\Shipping\Model\Config as ShippingConfig;

class CarrierIo implements \WiseRobot\Io\Api\CarrierIoInterface
{
    /**
     * @var ShippingConfig
     */
    public $shippingConfig;

    /**
     * @param ShippingConfig $shippingConfig
     */
    public function __construct(
        ShippingConfig $shippingConfig
    ) {
        $this->shippingConfig = $shippingConfig;
    }

    /**
     * Get Shipping Carriers
     *
     * @return array
     */
    public function getList(): array
    {
        $storeId = 0;
        $carriers = [];
        $carriers[]["custom"] = __("Custom Value");
        // get all system carriers
        $carrierInstances = $this->shippingConfig->getAllCarriers($storeId);
        foreach ($carrierInstances as $code => $carrier) {
            if ($carrier->isTrackingAvailable()) {
                $carriers[][$code] = $carrier->getConfigData("title");
            }
        }

        return $carriers;
    }
}
