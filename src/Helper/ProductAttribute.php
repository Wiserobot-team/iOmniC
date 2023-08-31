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

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\Eav\Model\Entity\Attribute\Source\TableFactory;
use Magento\Swatches\Helper\Data as SwatchesHelper;
use Magento\Swatches\Model\ResourceModel\Swatch\CollectionFactory as SwatchCollectionFactory;

class ProductAttribute extends \Magento\Framework\App\Helper\AbstractHelper
{
    public $attributes = [];
    public $productAttribute;

    public function __construct(
        ProductAttributeRepositoryInterface  $attributeRepository,
        AttributeOptionManagementInterface   $attributeOptionManagement,
        AttributeOptionInterfaceFactory      $attributeOptionFactory,
        AttributeOptionLabelInterfaceFactory $attributeOptionLabelFactory,
        TableFactory                         $attributeSourceTableFactory,
        SwatchesHelper                       $swatchesHelper,
        SwatchCollectionFactory              $swatchCollectionFactory

    ) {
        $this->attributeRepository           = $attributeRepository;
        $this->attributeOptionManagement     = $attributeOptionManagement;
        $this->attributeOptionFactory        = $attributeOptionFactory;
        $this->attributeOptionLabelFactory   = $attributeOptionLabelFactory;
        $this->attributeSourceTableFactory   = $attributeSourceTableFactory;
        $this->swatchesHelper                = $swatchesHelper;
        $this->swatchCollectionFactory       = $swatchCollectionFactory;
    }

    public function getAttributeOptionValue($attributeCode, $optionLabel)
    {
        $optionLabel = trim($optionLabel);
        if (!$optionLabel) {
            return false;
        }

        $attribute = $this->getAttribute($attributeCode);
        if (!$attribute) {
            return false;
        }

        if ($attribute->getData("frontend_input") != "select") {
            return false;
        }

        if (isset($this->attributes[$attributeCode]['labels'][$optionLabel])) {
            return $this->attributes[$attributeCode]['labels'][$optionLabel];
        } else {
            $attribute = $this->attributes[$attributeCode]['attribute'];
            $option    = $this->attributeOptionFactory->create();
            $option->setLabel($optionLabel);
            $option->setSortOrder(0);
            $option->setIsDefault(false);

            $this->attributeOptionManagement->add(
                \Magento\Catalog\Model\Product::ENTITY,
                $attribute->getAttributeCode(),
                $option
            );

            // force reload attribute
            unset($this->attributes[$attributeCode]);
            $this->getAttributeData($attributeCode);
            if (isset($this->attributes[$attributeCode]['labels'][$optionLabel])) {
                $optionId = $this->attributes[$attributeCode]['labels'][$optionLabel];
                $this->productAttribute->results["response"]["data"]["success"][] = "Add option '" . $optionLabel . "' to attribute '" . $attributeCode . "' as value id: " . $optionId;
                $this->log("Add option '" . $optionLabel . "' to attribute '" . $attributeCode . "' as value id: " . $optionId);
                $this->productAttribute->cleanResponseMessages();
                $this->addAttributeOptionSwatch($attribute, $optionId);

                return $this->attributes[$attributeCode]['labels'][$optionLabel];
            }
        }
    }

    public function getAttributeData($attributeCode)
    {
        if (!isset($this->attributes[$attributeCode])) {
            $this->attributes[$attributeCode] = [];

            $attribute = $this->attributeRepository->get($attributeCode);
            $this->attributes[$attributeCode]['attribute'] = $attribute;
        }

        if (!isset($this->attributes[$attributeCode]['labels'])) {
            $this->attributes[$attributeCode]['values'] = [];
            $this->attributes[$attributeCode]['labels'] = [];
            $sourceModel = $this->attributeSourceTableFactory->create();
            $sourceModel->setAttribute($this->attributes[$attributeCode]['attribute']);
            $options = $sourceModel->getAllOptions(false);
            foreach ($options as $option) {
                $label = trim($option['label']);
                $value = $option['value'];
                $this->attributes[$attributeCode]['values'][$value] = $label;
                $this->attributes[$attributeCode]['labels'][$label] = $value;
            }
        }
    }

    public function getAttribute($attributeCode)
    {
        $this->getAttributeData($attributeCode);

        if (isset($this->attributes[$attributeCode]['attribute'])) {
            $attribute = $this->attributes[$attributeCode]['attribute'];
        } else {
            $attribute = false;
        }
        return $attribute;
    }

    public function getAttributeOptions($attributeCode)
    {
        $this->getAttributeData($attributeCode);

        if (isset($this->attributes[$attributeCode]['labels'])) {
            return $this->attributes[$attributeCode]['labels'];
        }

        return [];
    }

    public function addAttributeOptionSwatch($attribute, $optionId)
    {
        // check if attribute is swatch visual
        $isSwatch = $this->swatchesHelper->isSwatchAttribute($attribute);
        if ($isSwatch) {
            $swatch = $this->loadSwatchIfExists($optionId);
            if (!$swatch->getId()) {
                $this->saveSwatchData($optionId);
            }
        }
    }

    public function loadSwatchIfExists($optionId, $storeId = 0)
    {
        $collection = $this->swatchCollectionFactory->create();
        $collection->addFieldToFilter('option_id', $optionId);
        $collection->addFieldToFilter('store_id', $storeId);
        $collection->setPageSize(1);

        $loadedSwatch = $collection->getFirstItem();

        return $loadedSwatch;
    }

    public function saveSwatchData($optionId, $storeId = 0, $type = 3, $value = NULL)
    {
        $swatch = $this->loadSwatchIfExists($optionId, 0);
        if (!$swatch->getId()) {
            $swatch->setData('option_id', $optionId);
            $swatch->setData('store_id', $storeId);
            $swatch->setData('type', $type);
            $swatch->setData('value', $value);
            try {
                $swatch->save();
            } catch (\Exception $e) {
            }
        }
    }

    public function log($message)
    {
        if ($this->productAttribute) {
            $this->productAttribute->log($message);
        }
    }
}
