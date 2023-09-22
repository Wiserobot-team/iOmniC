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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Shipping\Model\Config as ShippingConfig;

class ShippingIo implements \WiseRobot\Io\Api\ShippingIoInterface
{
    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfig;
    /**
     * @var ShippingConfig
     */
    public $shippingConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ShippingConfig $shippingConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ShippingConfig $shippingConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->shippingConfig = $shippingConfig;
    }

    /**
     * Get Shipping Methods
     *
     * @return array
     */
    public function getList(): array
    {
        $shipMethods = [];
        $activeCarriers = $this->shippingConfig->getActiveCarriers();
        foreach ($activeCarriers as $carrierCode => $carrierModel) {
            $carrierTitle = $this->scopeConfig->getValue(
                'carriers/' . $carrierCode . '/title'
            );
            $carrierMethods = $carrierModel->getAllowedMethods();
            if ($carrierMethods) {
                foreach ($carrierMethods as $methodCode => $methodLabel) {
                    if (is_array($methodLabel)) {
                        foreach ($methodLabel as $methodLabelKey => $methodLabelValue) {
                            $shipMethods[][$methodLabelKey] = $methodLabelValue;
                        }
                    } else {
                        $shipMethod = $carrierCode . "_" . $methodCode;
                        $shipMethodTitle = $carrierTitle . " - " . $methodLabel;
                        $shipMethods[][$shipMethod] = $shipMethodTitle;
                    }
                }
            }
        }

        return $shipMethods;
    }
}
