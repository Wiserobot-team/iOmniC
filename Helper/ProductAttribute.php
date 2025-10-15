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

namespace WiseRobot\Io\Helper;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Model\Entity\Attribute\Source\TableFactory;
use WiseRobot\Io\Model\ProductManagement;

class ProductAttribute extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var ProductAttributeRepositoryInterface
     */
    public $attributeRepository;
    /**
     * @var AttributeOptionManagementInterface
     */
    public $attributeOptionManagement;
    /**
     * @var AttributeOptionInterfaceFactory
     */
    public $attributeOptionFactory;
    /**
     * @var TableFactory
     */
    public $attributeSourceTableFactory;
    /**
     * @var ProductManagement|null
     */
    public ?ProductManagement $productManagement = null;
    /**
     * @var array
     */
    public array $attributes = [];

    /**
     * @param ProductAttributeRepositoryInterface $attributeRepository
     * @param AttributeOptionManagementInterface $attributeOptionManagement
     * @param AttributeOptionInterfaceFactory $attributeOptionFactory
     * @param TableFactory $attributeSourceTableFactory
     */
    public function __construct(
        ProductAttributeRepositoryInterface $attributeRepository,
        AttributeOptionManagementInterface $attributeOptionManagement,
        AttributeOptionInterfaceFactory $attributeOptionFactory,
        TableFactory $attributeSourceTableFactory
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->attributeOptionManagement = $attributeOptionManagement;
        $this->attributeOptionFactory = $attributeOptionFactory;
        $this->attributeSourceTableFactory = $attributeSourceTableFactory;
    }

    /**
     * Get or create attribute option value
     *
     * @param string $attributeCode
     * @param string $optionLabel
     * @return string|int|null
     */
    public function getAttributeOptionValue(
        string $attributeCode,
        string $optionLabel
    ): string|int|null {
        $optionLabel = trim($optionLabel);
        if (!$optionLabel) {
            $this->log("Empty option label provided for attribute '{$attributeCode}'");
            return null;
        }
        $attribute = $this->getAttribute($attributeCode);
        if (!$attribute) {
            $this->log("Attribute '{$attributeCode}' does not exist");
            return null;
        }
        $frontendInput = $attribute->getFrontendInput();
        if (!in_array($frontendInput, ['select', 'multiselect'])) {
            $message = "Attribute '{$attributeCode}' has frontend input '{$frontendInput}' . " .
                " which is not a selectable option type";
            $this->log($message);
            return null;
        }
        $this->getAttributeData($attributeCode);
        if (isset($this->attributes[$attributeCode]['labels'][$optionLabel])) {
            return $this->attributes[$attributeCode]['labels'][$optionLabel];
        }
        try {
            $newOptionId = $this->createAttributeOption($attribute, $optionLabel);
            if ($newOptionId) {
                $message  = "Add option '{$optionLabel}' to '{$attributeCode}' as value id: {$newOptionId}";
                $this->handleResult($message, 'success');
                return $newOptionId;
            }
        } catch (\Exception $e) {
            $this->log("Failed to create option '{$optionLabel}' for attribute '{$attributeCode}': "
                . $e->getMessage());
        }
        return null;
    }

    /**
     * Creates a new option for a select attribute
     *
     * @param ProductAttributeInterface $attribute
     * @param string $optionLabel
     * @return string|int|null
     */
    public function createAttributeOption(
        ProductAttributeInterface $attribute,
        string $optionLabel
    ): string|int|null {
        $option = $this->attributeOptionFactory->create();
        $option->setLabel($optionLabel);
        $option->setSortOrder(0);
        $option->setIsDefault(false);
        $this->attributeOptionManagement->add(
            Product::ENTITY,
            $attribute->getAttributeCode(),
            $option
        );
        $this->reloadAttributeData($attribute->getAttributeCode());
        if (isset($this->attributes[$attribute->getAttributeCode()]['labels'][$optionLabel])) {
            return $this->attributes[$attribute->getAttributeCode()]['labels'][$optionLabel];
        }
        return null;
    }

    /**
     * Force reload attribute data
     *
     * @param string $attributeCode
     * @return void
     */
    public function reloadAttributeData(string $attributeCode): void
    {
        unset($this->attributes[$attributeCode]);
        $this->getAttributeData($attributeCode);
    }

    /**
     * Get attribute data by attribute code
     *
     * @param string $attributeCode
     * @return void
     */
    public function getAttributeData(string $attributeCode): void
    {
        if (!isset($this->attributes[$attributeCode])) {
            $this->attributes[$attributeCode] = [];
            try {
                $attribute = $this->attributeRepository->get($attributeCode);
                $this->attributes[$attributeCode]['attribute'] = $attribute;
            } catch (\Exception $e) {
                return;
            }
        }
        if (!isset($this->attributes[$attributeCode]['labels']) && isset($this->attributes[$attributeCode]['attribute'])) {
            $attribute = $this->attributes[$attributeCode]['attribute'];
            $this->attributes[$attributeCode]['values'] = [];
            $this->attributes[$attributeCode]['labels'] = [];
            if ($attribute->getFrontendInput() === 'select' || $attribute->getFrontendInput() === 'multiselect') {
                $sourceModel = $this->attributeSourceTableFactory->create();
                $sourceModel->setAttribute($attribute);
                $options = $sourceModel->getAllOptions(false);
                foreach ($options as $option) {
                    $label = trim((string) $option['label']);
                    $value = $option['value'];
                    if (!empty($value)) {
                        $this->attributes[$attributeCode]['values'][(string) $value] = $label;
                        $this->attributes[$attributeCode]['labels'][$label] = (string) $value;
                    }
                }
            }
        }
    }

    /**
     * Get attribute by attribute code
     *
     * @param string $attributeCode
     * @return ProductAttributeInterface|null
     */
    public function getAttribute(string $attributeCode): ?ProductAttributeInterface
    {
        $this->getAttributeData($attributeCode);
        $attribute = $this->attributes[$attributeCode]['attribute'] ?? null;
        return ($attribute instanceof ProductAttributeInterface) ? $attribute : null;
    }

    /**
     * Handles logging and saving the result to ProductManagement
     *
     * @param string $message
     * @param string $type
     * @return void
     */
    public function handleResult(string $message, string $type): void
    {
        if ($this->productManagement !== null) {
            $this->productManagement->results["response"]["data"][$type][] = $message;
            $this->productManagement->cleanResponseMessages();
        }
        $this->log($message);
    }

    /**
     * Logs a message
     *
     * @param string $message
     * @return void
     */
    public function log(string $message): void
    {
        if ($this->productManagement !== null) {
            $this->productManagement->log($message);
        }
    }
}
