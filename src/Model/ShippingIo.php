<?php
/**
* WISEROBOT INDUSTRIES SDN. BHD. **NOTICE OF LICENSE**
* This source file is subject to the EULA that is bundled with this package in the file LICENSE.pdf. It is also available through the world-wide-web at this URL:
* http://wiserobot.com/mage_extension_license.pdf
* =================================================================
* MAGENTO COMMUNITY EDITION USAGE NOTICE
* =================================================================
* This package is designed for the Magento COMMUNITY edition
* This extension may not work on any other Magento edition except Magento COMMUNITY edition. WiseRobot does not provide extension support in case of incorrect edition usage.
* =================================================================
* Copyright (c) 2019 WISEROBOT INDUSTRIES SDN. BHD. (http://www.wiserobot.com)
* License http://wiserobot.com/mage_extension_license.pdf
*
*/
namespace Wiserobot\Io\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Shipping\Model\Config as ShippingConfig;

class ShippingIo implements \Wiserobot\Io\Api\ShippingIoInterface
{
    public function __construct(
        RequestInterface      $request,
        ScopeConfigInterface  $scopeConfig,
        ShippingConfig        $shippingConfig

    ) {
        $this->request        = $request;
        $this->scopeConfig    = $scopeConfig;
        $this->shippingConfig = $shippingConfig;
    }

    public function shippings()
    {
        $shipMethods    = [];
        // $activeCarriers = $this->shippingConfig->getAllCarriers();
        $activeCarriers = $this->shippingConfig->getActiveCarriers();
        foreach ($activeCarriers as $carrierCode => $carrierModel) {
            $carrierTitle = $this->scopeConfig->getValue('carriers/' . $carrierCode . '/title');
            if ($carrierMethods = $carrierModel->getAllowedMethods()) {
                foreach ($carrierMethods as $methodCode => $methodLabel) {
                    if (is_array($methodLabel)) {
                        foreach ($methodLabel as $methodLabelKey => $methodLabelValue) {
                            $shipMethods[][$methodLabelKey] = $methodLabelValue;
                        }
                    } else {
                        $shipMethod                 = $carrierCode . "_" . $methodCode;
                        $shipMethodTitle            = $carrierTitle . " - " . $methodLabel;
                        $shipMethods[][$shipMethod] = $shipMethodTitle;
                    }
                }
            }
        }

        return $shipMethods;
    }
}
