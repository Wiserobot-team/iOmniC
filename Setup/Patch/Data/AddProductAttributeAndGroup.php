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

namespace WiseRobot\Io\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Catalog\Model\Product;

/**
 * Data patch to create a new product attribute and assign it to a new custom group
 * within the 'Default' attribute set
 */
class AddProductAttributeAndGroup implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var AttributeSetFactory
     */
    protected $attributeSetFactory;

    /**
     * Define constants for the new attribute and group.
     */
    private const ATTRIBUTE_CODE = 'io_images';
    private const ATTRIBUTE_LABEL = 'iOmniC Images';
    private const ATTRIBUTE_GROUP = 'iOmniC';
    private const ENTITY_TYPE_ID = Product::ENTITY;
    private const ATTRIBUTE_SET_NAME = 'Default';

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     * @param AttributeSetFactory $attributeSetFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory,
        AttributeSetFactory $attributeSetFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
    }

    /**
     * Apply the data changes: create attribute, create group, and assign attribute to group.
     * @return AddProductAttributeAndGroup
     */
    public function apply(): AddProductAttributeAndGroup
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $entityTypeId = $eavSetup->getEntityTypeId(self::ENTITY_TYPE_ID);
        $attributeSetId = $eavSetup->getAttributeSetId($entityTypeId, self::ATTRIBUTE_SET_NAME);

        $eavSetup->addAttributeGroup(
            $entityTypeId,
            $attributeSetId,
            self::ATTRIBUTE_GROUP,
            10
        );

        $eavSetup->addAttribute(
            self::ENTITY_TYPE_ID,
            self::ATTRIBUTE_CODE,
            [
                'type' => 'text',
                'label' => self::ATTRIBUTE_LABEL,
                'input' => 'textarea',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => ''
            ]
        );

        $eavSetup->addAttributeToGroup(
            self::ENTITY_TYPE_ID,
            $attributeSetId,
            self::ATTRIBUTE_GROUP,
            self::ATTRIBUTE_CODE,
            30
        );

        return $this;
    }

    /**
     * Defines the list of patches that must be executed before this patch runs
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Aliases are the names for the patch, that should be considered as the same
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
