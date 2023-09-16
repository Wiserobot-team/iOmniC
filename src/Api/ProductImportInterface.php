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

interface ProductImportInterface
{
    /**
     * Create or update Product
     *
     * @param int $store
     * @param string[] $attributeInfo
     * @param string[] $variationInfo
     * @param string[] $groupedInfo
     * @param string[] $stockInfo
     * @param string[] $imageInfo
     * @return array
     */
    public function import(
        int $store,
        array $attributeInfo,
        array $variationInfo,
        array $groupedInfo = [],
        array $stockInfo = [],
        array $imageInfo = []
    ): array;
}
