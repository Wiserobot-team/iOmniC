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
namespace Wiserobot\Io\Helper;

use Magento\Catalog\Api\ProductRepositoryInterface;

class Sku extends \Magento\Framework\App\Helper\AbstractHelper
{
    public function __construct(
        ProductRepositoryInterface $productRepository
    ) {
        $this->productRepository   = $productRepository;
    }

    /**
     * return product object or false.
     */
    public function loadBySku($sku, $storeId = 0)
    {
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

    /**
     * return product object or false.
     */
    public function loadById($productId, $storeId = 0)
    {
        try {
            if ($storeId) {
                $product = $this->productRepository->getById($productId, false, $storeId);
            } else {
                $product = $this->productRepository->getById($productId);
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
