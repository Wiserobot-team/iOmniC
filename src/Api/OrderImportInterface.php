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
namespace Wiserobot\Io\Api;

interface OrderImportInterface
{
    /**
     * Create or update order
     *
     * @param int $store
     * @param string[] $order_info
     * @param string[] $payment_info
     * @param string[] $shipping_info
     * @param string[] $billing_info
     * @param mixed $status_histories
     * @param mixed $shipment_info
     * @param mixed $refund_info
     * @param mixed $item_info
     * @return array
     */
    public function import($store, $order_info, $payment_info, $shipping_info, $billing_info, $status_histories = [], $shipment_info = [], $refund_info = [], $item_info);
}
