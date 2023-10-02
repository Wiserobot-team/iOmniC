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

namespace WiseRobot\Io\Helper;

use Magento\Catalog\Api\ProductRepositoryInterface;

class Sku extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var ProductRepositoryInterface
     */
    public $productRepository;
    /**
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        ProductRepositoryInterface $productRepository
    ) {
        $this->productRepository = $productRepository;
    }

    /**
     * Load product by product sku
     *
     * @param string $sku
     * @param int $storeId
     * @return false|\Magento\Catalog\Api\Data\ProductInterface
     */
    public function loadBySku(
        $sku,
        $storeId = 0
    ) {
        try {
            if ($storeId) {
                $product = $this->productRepository->get($sku, false, $storeId);
            } else {
                $product = $this->productRepository->get($sku);
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $product = false;
        }
        if (!$product || !$product->getId()) {
            return false;
        }

        return $product;
    }
}
