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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Config as PaymentConfig;

class PaymentIo implements \Wiserobot\Io\Api\PaymentIoInterface
{
    public $scopeConfig;
    public $paymentConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        PaymentConfig        $paymentConfig

    ) {
        $this->scopeConfig   = $scopeConfig;
        $this->paymentConfig = $paymentConfig;
    }

    public function payments()
    {
        $paymentMethodArray = [];
        $payments           = $this->paymentConfig->getActiveMethods();
        foreach ($payments as $paymentCode => $paymentModel) {
            $paymentTitle = $this->scopeConfig->getValue('payment/' . $paymentCode . '/title');
            if (!$paymentTitle) {
                continue;
            }
            $paymentMethodArray[][$paymentCode] = $paymentTitle;
        }

        return $paymentMethodArray;
    }
}
