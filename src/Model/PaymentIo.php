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

namespace WiseRobot\Io\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Config as PaymentConfig;

class PaymentIo implements \WiseRobot\Io\Api\PaymentIoInterface
{
    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfig;
    /**
     * @var PaymentConfig
     */
    public $paymentConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param PaymentConfig $paymentConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        PaymentConfig        $paymentConfig
    ) {
        $this->scopeConfig   = $scopeConfig;
        $this->paymentConfig = $paymentConfig;
    }

    /**
     * Get Payment Methods
     *
     * @return array
     */
    public function getList()
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
