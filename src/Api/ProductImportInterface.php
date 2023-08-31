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

interface ProductImportInterface
{
    /**
     * Create or update product
     *
     * @param int $store
     * @param string[] $attribute_info
     * @param string[] $variation_info
     * @param string[] $grouped_info
     * @param string[] $stock_info
     * @param string[] $image_info
     * @return array
     */
    public function import($store, $attribute_info, $variation_info, $grouped_info = [], $stock_info = [], $image_info = []);
}
