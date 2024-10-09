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

namespace WiseRobot\Io\Model;

use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Tax\Model\ClassModelFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as EntityAttributeSetFactory;
use Magento\Catalog\Model\CategoryFactory;
use Magento\ConfigurableProduct\Model\Product\Type\ConfigurableFactory as ConfigurableProduct;
use Magento\GroupedProduct\Model\Product\Type\GroupedFactory as GroupedProduct;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Webapi\Exception as WebapiException;

class ProductIo implements \WiseRobot\Io\Api\ProductIoInterface
{
    /**
     * @var array
     */
    public array $results = [];
    /**
     * @var bool
     */
    public $selectAll = false;
    /**
     * @var array
     */
    public array $selectAttrs = [];
    /**
     * @var ProductFactory
     */
    public $productFactory;
    /**
     * @var ProductCollectionFactory
     */
    public $productCollectionFactory;
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;
    /**
     * @var TimezoneInterface
     */
    public $timezoneInterface;
    /**
     * @var ClassModelFactory
     */
    public $classModelFactory;
    /**
     * @var EntityAttributeSetFactory
     */
    public $entityAttributeSetFactory;
    /**
     * @var CategoryFactory
     */
    public $categoryFactory;
    /**
     * @var ConfigurableProduct
     */
    public $configurableProduct;
    /**
     * @var GroupedProduct
     */
    public $groupedProduct;
    /**
     * @var AttributeRepositoryInterface
     */
    public $attributeRepositoryInterface;
    /**
     * @var ResourceConnection
     */
    public $resourceConnection;

    /**
     * @param ProductFactory $productFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param TimezoneInterface $timezoneInterface
     * @param ClassModelFactory $classModelFactory
     * @param EntityAttributeSetFactory $entityAttributeSetFactory
     * @param CategoryFactory $categoryFactory
     * @param ConfigurableProduct $configurableProduct
     * @param GroupedProduct $groupedProduct
     * @param AttributeRepositoryInterface $attributeRepositoryInterface
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ProductFactory $productFactory,
        ProductCollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        TimezoneInterface $timezoneInterface,
        ClassModelFactory $classModelFactory,
        EntityAttributeSetFactory $entityAttributeSetFactory,
        CategoryFactory $categoryFactory,
        ConfigurableProduct $configurableProduct,
        GroupedProduct $groupedProduct,
        AttributeRepositoryInterface $attributeRepositoryInterface,
        ResourceConnection $resourceConnection
    ) {
        $this->productFactory = $productFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->timezoneInterface = $timezoneInterface;
        $this->classModelFactory = $classModelFactory;
        $this->entityAttributeSetFactory = $entityAttributeSetFactory;
        $this->categoryFactory = $categoryFactory;
        $this->configurableProduct = $configurableProduct;
        $this->groupedProduct = $groupedProduct;
        $this->attributeRepositoryInterface = $attributeRepositoryInterface;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @var string[]
     */
    public $defaultAttributes = [
        'name',
        'tax_class_id',
        'visibility',
        'status',
        'price',
        'image',
        'image_label',
        'small_image',
        'small_image_label',
        'thumbnail',
        'thumbnail_label',
        'swatch_image'
    ];

    /**
     * @var string[]
     */
    public $customAttributes = [
        "category_name" => "Category Name",
        "category_tree" => "Category Tree",
        "category_ids" => "Category Ids"
    ];

    /**
     * @var string[]
     */
    public $floatAttributes = [
        "price" => "Price",
        "special_price" => "Special Price",
        "tier_price" => "Tier Price",
        "cost" => "Cost",
        "weight" => "Weight"
    ];

    /**
     * @var string[]
     */
    public $ignoreAttributes = [
        'store_id',
        'entity_id',
        'sku',
        'name',
        'attribute_set_id',
        'visibility',
        'tax_class_id',
        'type_id',
        'created_at',
        'updated_at',
        'status',
        'price',
        'qty',
        'quantity_and_stock_status',
        'is_dropship_item',
        'image',
        'small_image',
        'thumbnail',
        'swatch_image',
        'media_gallery'
    ];

    /**
     * Filter Product Data
     *
     * @param int $store
     * @param string $select
     * @param string $filter
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getList(
        int $store,
        string $select = "*",
        string $filter = "",
        int $page = 1,
        int $limit = 100
    ): array {
        $storeInfo = $this->getStoreInfo($store);
        $productCollection = $this->createProductCollection($store);
        $this->applySelectAttributes($productCollection, $select);
        $this->applyFilter($productCollection, $filter);
        $this->applySortingAndPaging($productCollection, $page, $limit);
        $this->addMediaGallery($productCollection);
        $result = [];
        $storeId = (int) $storeInfo->getId();
        $storeName = $storeInfo->getName();
        foreach ($productCollection as $product) {
            $sku = $product->getData("sku");
            if ($sku) {
                $productData = $this->formatProductData($product, $storeId);
                if (!empty($productData)) {
                    $productData['store_id'] = $storeId;
                    $productData['store'] = $storeName;
                    $result[$sku] = $productData;
                }
            }
        }
        return $result;
    }

    /**
     * Get Store Info
     *
     * @param int $store
     * @return \Magento\Store\Model\Store
     */
    public function getStoreInfo(
        int $store
    ): \Magento\Store\Model\Store {
        try {
            return $this->storeManager->getStore($store);
        } catch (\Exception $e) {
            $message = "Requested 'store' {$store} doesn't exist";
            $this->results["error"] = $message;
            throw new WebapiException(__($message), 0, 400, $this->results);
        }
    }

    /**
     * Create product collection with basic filters
     *
     * @param int $store
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function createProductCollection(
        int $store
    ): \Magento\Catalog\Model\ResourceModel\Product\Collection {
        $productCollection = $this->productCollectionFactory->create();
        $productCollection->addStoreFilter($store)
            ->joinTable(
                [$this->resourceConnection->getTableName('cataloginventory_stock_item')],
                'product_id=entity_id',
                ['qty', 'min_sale_qty'],
                'stock_id = 1',
                'left'
            );
        return $productCollection;
    }

    /**
     * Apply selected attributes to the product collection
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection
     * @param string $select
     * @return void
     */
    public function applySelectAttributes(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection,
        string $select
    ): void {
        $select = trim($select);
        if ($select === '' || $select === '*') {
            $this->selectAll = true;
            $productCollection->addAttributeToSelect('*');
        } else {
            $this->selectAttrs = array_map('trim', explode(',', $select));
            $productCollection->addAttributeToSelect(array_merge($this->defaultAttributes, $this->selectAttrs));
        }
    }

    /**
     * Apply filter to the product collection
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection
     * @param string $filter
     * @return void
     */
    public function applyFilter(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection,
        string $filter
    ): void {
        $filter = trim($filter);
        $filterArray = explode(" and ", $filter);
        foreach ($filterArray as $filterItem) {
            $operator = $this->processFilter($filterItem);
            if (!$operator) {
                continue;
            }
            $condition = array_map('trim', explode($operator, $filterItem));
            if (count($condition) != 2 || !$condition[0] || !$condition[1]) {
                continue;
            }
            $fieldName = $condition[0];
            $fieldValue = $condition[1];
            try {
                $this->attributeRepositoryInterface->get('catalog_product', $fieldName);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                $message = "Field: 'filter' - attribute '{$fieldName}' doesn't exist";
                $this->results["error"] = $message;
                throw new WebapiException(__($message), 0, 400, $this->results);
            }
            if ($operator === "in") {
                $fieldValue = array_map('trim', explode(",", $fieldValue));
            }
            $productCollection->addFieldToFilter(
                $fieldName,
                [$operator => $fieldValue]
            );
        }
    }

    /**
     * Process filter data
     *
     * @param string $string
     * @return string
     */
    public function processFilter(string $string): string
    {
        $operators = [
            ' eq ' => 'eq',
            ' gt ' => 'gt',
            ' le ' => 'le',
            ' in ' => 'in',
        ];
        foreach ($operators as $key => $operator) {
            if (strpos($string, $key) !== false) {
                return $operator;
            }
        }
        return '';
    }

    /**
     * Apply sorting and paging to the product collection
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection
     * @param int $page
     * @param int $limit
     * @return void
     */
    public function applySortingAndPaging(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection,
        int $page,
        int $limit
    ): void {
        $productCollection->setOrder('entity_id', 'asc')
            ->setPageSize(min(max(1, (int) $limit), 100))
            ->setCurPage(max(1, (int) $page));
    }

    /**
     * Add media gallery to the product collection
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection
     * @return void
     */
    public function addMediaGallery(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection
    ): void {
        $productCollection->addMediaGalleryData();
    }

    /**
     * Get Product Data
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return array
     */
    public function formatProductData(
        \Magento\Catalog\Model\Product $product,
        int $storeId
    ): array {
        $productId = (int) $product->getData("entity_id");
        $typeId = $product->getData("type_id");
        $productData = [
            'attribute_info' => [
                'id' => $productId,
                'sku' => $product->getData("sku"),
                'name' => $product->getData("name"),
                'attribute_set' => $this->getAttrValue($product, 'attribute_set_id', $storeId),
                'visibility' => $this->getAttrValue($product, 'visibility', $storeId),
                'tax_class' => $this->getAttrValue($product, 'tax_class_id', $storeId),
                'type_id' => $typeId,
                'created_at' => $product->getData("created_at"),
                'updated_at' => $product->getData("updated_at"),
                'status' => $this->getAttrValue($product, 'status', $storeId),
                'price' => (float) $this->getAttrValue($product, 'price', $storeId)
            ]
        ];
        $this->populateImageAttributes($productData, $product);
        $this->populateImageInfo($productData, $product);
        if ($typeId !== "grouped") {
            $productData['variation_info'] = $this->populateVariationInfo($product, $storeId);
        }
        if ($typeId !== "configurable") {
            $productData['grouped_info'] = $this->populateGroupedProductInfo($product, $storeId);
        }
        $this->populateProductLinks($productData, $product);
        $this->populateStockInfo($productData, $product);
        $this->populateCategories($productData, $product, $storeId);
        $this->populateProductAttributes($productData, $product, $storeId);
        return $productData;
    }

    /**
     * Populate Image Attributes
     *
     * @param array $productData
     * @param \Magento\Catalog\Model\Product $product
     * @return void
     */
    public function populateImageAttributes(
        array &$productData,
        \Magento\Catalog\Model\Product $product
    ): void {
        if ($this->selectAll || in_array("image_attributes", $this->selectAttrs)) {
            $imageAttributes = [
                'image' => 'base_image',
                'image_label' => 'base_image_label',
                'small_image' => 'small_image',
                'small_image_label' => 'small_image_label',
                'thumbnail' => 'thumbnail_image',
                'thumbnail_label' => 'thumbnail_image_label',
                'swatch_image' => 'swatch_image',
            ];
            foreach ($imageAttributes as $attrCode => $attrName) {
                $productData['attribute_info'][$attrName] = $this->getImgAttr($product, $attrCode);
            }
            $productData['attribute_info']['swatch_image_label'] = '';
            $gallery = $product->getMediaGalleryImages();
            if ($gallery && $gallery->getSize()) {
                $additionalImage = [];
                $additionalImageLabels = [];
                foreach ($gallery as $image) {
                    $additionalImage[] = $image->getFile();
                    $additionalImageLabels[] = $image->getLabel();
                }
                $productData['attribute_info']['additional_images'] = implode(',', $additionalImage);
                $productData['attribute_info']['additional_image_labels'] = implode(',', $additionalImageLabels);
            } else {
                $productData['attribute_info']['additional_images'] = '';
                $productData['attribute_info']['additional_image_labels'] = '';
            }
        }
    }

    /**
     * Get Image Attributes
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $attrCode
     * @return string|null
     */
    public function getImgAttr(
        \Magento\Catalog\Model\Product $product,
        string $attrCode
    ): string|null {
        return $product->getResource()->getAttribute($attrCode)?->getFrontend()->getValue($product) ?: null;
    }

    /**
     * Populate Image Info
     *
     * @param array $productData
     * @param \Magento\Catalog\Model\Product $product
     * @return void
     */
    public function populateImageInfo(
        array &$productData,
        \Magento\Catalog\Model\Product $product,
    ): void {
        $imageInfo = [];
        $gallery = $product->getMediaGalleryImages();
        if ($gallery && $gallery->getSize()) {
            foreach ($gallery as $image) {
                $imageInfo[] = [
                    'position' => $image->getPosition(),
                    'url' => $image->getUrl()
                ];
            }
        }
        if (!empty($imageInfo)) {
            $productData['image_info'] = $imageInfo;
        }
    }

    /**
     * Populate Categories
     *
     * @param array $productData
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return void
     */
    public function populateCategories(
        array &$productData,
        \Magento\Catalog\Model\Product $product,
        int $storeId
    ): void {
        if ($this->selectAll || in_array("categories", $this->selectAttrs)) {
            $productData['attribute_info']['categories'] = $this->getCategoryTree($product, $storeId);
        }
    }

    /**
     * Populate Product Attributes
     *
     * @param array $productData
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return void
     */
    public function populateProductAttributes(
        array &$productData,
        \Magento\Catalog\Model\Product $product,
        int $storeId
    ): void {
        $attributes = $this->selectAll ? array_keys($product->getData()) : $this->selectAttrs;
        foreach ($attributes as $attrCode) {
            if (in_array($attrCode, $this->ignoreAttributes)) {
                continue;
            }
            if (!$product->getResource()->getAttribute($attrCode) &&
                !isset($this->customAttributes[$attrCode])) {
                continue;
            }
            $attrData = $this->getAttrValue($product, $attrCode, $storeId);
            if (isset($this->floatAttributes[$attrCode]) && $attrData) {
                $attrData = floatval($attrData);
            }
            $productData['attribute_info'][$attrCode] = $attrData;
        }
    }

    /**
     * Get Attribute Value
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $attrCode
     * @param int $storeId
     * @return mixed
     */
    public function getAttrValue(
        \Magento\Catalog\Model\Product $product,
        string $attrCode,
        int $storeId
    ): mixed {
        $attrData = $product->getData($attrCode);
        switch ($attrCode) {
            case 'price':
                return $product->getPrice();
            case 'special_price':
                return $this->getSpecialPrice($product);
            case 'tax_class_id':
                $taxClassModel = $this->classModelFactory->create()->load($attrData);
                return $taxClassModel->getClassName();
            case 'attribute_set_id':
                $attributeSet = $this->entityAttributeSetFactory->create()->load($product->getAttributeSetId());
                return $attributeSet->getAttributeSetName();
            case 'visibility':
                return $this->getVisibility($attrData);
            case 'status':
                return $attrData == 2 ? 'Disabled' : 'Enabled';
            default:
                return $this->getCustomAttributeValue($product, $attrCode, $attrData, $storeId);
        }
    }

    /**
     * Get Special Price
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return mixed
     */
    public function getSpecialPrice(\Magento\Catalog\Model\Product $product): mixed
    {
        if ($product->getData('special_price')) {
            $now = strtotime((string) $this->timezoneInterface->date()->format('Y-m-d H:i:s'));
            $specialToDate = strtotime((string) $product->getData('special_to_date'));
            if ($specialToDate && $now > $specialToDate) {
                return $product->getPrice();
            }
            return $product->getTypeId() == 'bundle'
                ? $product->getPrice() * $product->getSpecialPrice() / 100
                : $product->getSpecialPrice();
        }
        return $product->getPrice();
    }

    /**
     * Get Visibility
     *
     * @param string $visibility
     * @return string
     */
    public function getVisibility(string $visibility): string
    {
        return match ($visibility) {
            '4' => 'Catalog, Search',
            '3' => 'Search',
            '2' => 'Catalog',
            default => 'Not Visible Individually',
        };
    }

    /**
     * Get Custom Attribute Value
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $attrCode
     * @param mixed $attrData
     * @param int $storeId
     * @return mixed
     */
    public function getCustomAttributeValue(
        \Magento\Catalog\Model\Product $product,
        string $attrCode,
        mixed $attrData,
        int $storeId
    ): mixed {
        if (isset($this->customAttributes[$attrCode])) {
            return $this->getCategoryAttributeValue($product, $attrCode, $storeId);
        }
        $attribute = $product->getResource()->getAttribute($attrCode);
        if (!$attribute) {
            return null;
        }
        $attrFrontendInput = $attribute->getFrontendInput();
        if (in_array($attrFrontendInput, ['select', 'multiselect'])) {
            return $attribute->setStoreId($storeId)->getFrontend()->getValue($product);
        }
        if ($attrFrontendInput == 'media_image' && $attrData) {
            return $product->getMediaConfig()->getMediaUrl($attrData);
        }
        return $attrData;
    }

    /**
     * Get Category Attribute Value
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $attrCode
     * @param int $storeId
     * @return string
     */
    public function getCategoryAttributeValue(
        \Magento\Catalog\Model\Product $product,
        string $attrCode,
        int $storeId
    ): string {
        return match ($attrCode) {
            'category_name' => $this->getCategoryName($product, $storeId),
            'category_tree' => $this->getCategoryTree($product, $storeId),
            'category_ids' => implode(',', $product->getCategoryIds()),
            default => '',
        };
    }

    /**
     * Get Category Name
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return string
     */
    public function getCategoryName(
        \Magento\Catalog\Model\Product $product,
        int $storeId
    ): string {
        $catIds = $product->getCategoryIds();
        if (!empty($catIds)) {
            $catId = end($catIds);
            $category = $this->categoryFactory->create()
                ->setStoreId($storeId)
                ->load($catId);
            return $category->getName() ?: '';
        }
        return '';
    }

    /**
     * Get Category Tree
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return string
     */
    public function getCategoryTree(
        \Magento\Catalog\Model\Product $product,
        int $storeId
    ): string {
        $categoryIds = $product->getCategoryIds();
        $categoryTreeMap = [];
        foreach ($categoryIds as $categoryId) {
            if ($categoryId > 0) {
                $category = $this->categoryFactory->create()
                    ->setStoreId($storeId)
                    ->load($categoryId);
                $categoryTree = $this->buildCategoryPath($category, $storeId);
                if ($categoryTree) {
                    $categoryTreeMap[$category->getId()] = $categoryTree;
                }
            }
        }
        // Remove parent categories if they exist in the array
        foreach ($categoryTreeMap as $catId => $catTree) {
            $category = $this->categoryFactory->create()
                ->setStoreId($storeId)
                ->load($catId);
            foreach ($category->getParentIds() as $parentCatId) {
                if (isset($categoryTreeMap[$parentCatId])) {
                    unset($categoryTreeMap[$parentCatId]);
                }
            }
        }
        return implode(',', $categoryTreeMap);
    }

    /**
     * Build Category Path
     *
     * @param \Magento\Catalog\Model\Category $category
     * @param int $storeId
     * @return string
     */
    public function buildCategoryPath(
        \Magento\Catalog\Model\Category $category,
        int $storeId
    ): string {
        $categoryTree = '';
        foreach ($category->getParentIds() as $parentCatId) {
            if ($parentCatId == 1) {
                continue;
            }
            $parentCategory = $this->categoryFactory->create()
                ->setStoreId($storeId)
                ->load($parentCatId);
            if ($parentCategory->getName()) {
                $categoryTree .= $parentCategory->getName() . '/';
            }
        }
        return $category->getName() ? $categoryTree . $category->getName() : '';
    }

    /**
     * Populate Variation Info
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return array
     */
    public function populateVariationInfo(
        \Magento\Catalog\Model\Product $product,
        int $storeId
    ): array {
        $variationInfo = [
            'is_in_relationship' => false,
            'is_parent' => false,
            'parent_sku' => '',
            'super_attribute' => '',
            'child_sku' => ''
        ];
        $typeId = $product->getTypeId();
        if ($typeId === 'configurable') {
            $variationInfo = [
                'is_in_relationship' => true,
                'is_parent' => true,
                'parent_sku' => $product->getSku(),
                'super_attribute' => $this->getRelationshipName($product) ?: ''
            ];
            $childProductIds = $this->configurableProduct->create()->getUsedProductIds($product);
            if (!empty($childProductIds)) {
                $productCollection = $this->productCollectionFactory->create()
                    ->addStoreFilter($storeId)
                    ->addAttributeToSelect('sku')
                    ->addFieldToFilter('entity_id', ['in' => $childProductIds]);
                $childProductSkus = $productCollection->getColumnValues('sku');
                if (!empty($childProductSkus)) {
                    sort($childProductSkus);
                    $variationInfo['child_sku'] = implode(',', $childProductSkus);
                }
            }
        } elseif ($typeId === 'simple' || $typeId === 'virtual') {
            $parentIds = $this->configurableProduct->create()
                ->getParentIdsByChild($product->getId());
            if (!empty($parentIds)) {
                $parentProduct = $this->productFactory->create()
                    ->setStoreId($storeId)
                    ->load($parentIds[0]);
                if ($parentProduct->getId()) {
                    $variationInfo = [
                        'is_in_relationship' => true,
                        'is_parent' => false,
                        'parent_sku' => $parentProduct->getSku(),
                        'super_attribute' => $this->getRelationshipName($parentProduct) ?: ''
                    ];
                }
            }
        }
        return $variationInfo;
    }

    /**
     * Get Configurable Product Attributes
     *
     * @param \Magento\Catalog\Model\Product $parentConfigurableProduct
     * @return string
     */
    public function getRelationshipName(
        \Magento\Catalog\Model\Product $parentConfigurableProduct
    ): string {
        if ($parentConfigurableProduct->getTypeId() !== "configurable") {
            return '';
        }
        $productAttributeOptions = $this->configurableProduct->create()
            ->getConfigurableAttributesAsArray($parentConfigurableProduct);
        if (empty($productAttributeOptions)) {
            return '';
        }
        $productConfigurableAttrs = array_column($productAttributeOptions, 'attribute_code');
        if (empty($productConfigurableAttrs)) {
            return '';
        }
        sort($productConfigurableAttrs);
        return implode(',', $productConfigurableAttrs);
    }

    /**
     * Populate Grouped Product Info
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return array
     */
    public function populateGroupedProductInfo(
        \Magento\Catalog\Model\Product $product,
        int $storeId
    ): array {
        $groupedInfo = [
            'is_parent' => false,
            'parent_sku' => '',
            'child_sku' => ''
        ];
        $productType = $product->getTypeId();
        if ($productType === 'grouped') {
            $groupedInfo['is_parent'] = true;
            $childProductIds = $this->groupedProduct->create()->getChildrenIds($product->getId());
            if (!empty($childProductIds[3])) {
                $productCollection = $this->productCollectionFactory->create()
                    ->addStoreFilter($storeId)
                    ->addAttributeToSelect('sku')
                    ->addFieldToFilter('entity_id', ['in' => $childProductIds[3]]);
                $childProductSkus = $productCollection->getColumnValues('sku');
                if (!empty($childProductSkus)) {
                    sort($childProductSkus);
                    $groupedInfo['child_sku'] = implode(',', $childProductSkus);
                }
            }
        } elseif (in_array($productType, ['simple', 'virtual'])) {
            $parentIds = $this->groupedProduct->create()->getParentIdsByChild($product->getId());
            if (!empty($parentIds)) {
                $parentProduct = $this->productFactory->create()->setStoreId($storeId)->load($parentIds[0]);
                if ($parentProduct->getId()) {
                    $groupedInfo['parent_sku'] = $parentProduct->getSku();
                }
            }
        }
        return $groupedInfo;
    }

    /**
     * Populate Product Links
     *
     * @param array $productData
     * @param \Magento\Catalog\Model\Product $product
     * @return void
     */
    public function populateProductLinks(
        array &$productData,
        \Magento\Catalog\Model\Product $product
    ): void {
        $linkTypes = [
            'related' => $product->getRelatedProducts(),
            'upsell' => $product->getUpSellProducts(),
            'crosssell' => $product->getCrossSellProducts()
        ];
        foreach ($linkTypes as $type => $products) {
            $productLinks = $this->getProductLinks($products);
            $productData['product_link_info'][$type . '_skus'] = implode(",", $productLinks['skus']);
            $productData['product_link_info'][$type . '_position'] = implode(",", $productLinks['positions']);
        }
    }

    /**
     * Get Product Links
     *
     * @param array $products
     * @return array
     */
    public function getProductLinks(array $products): array
    {
        $skus = [];
        $positions = [];
        foreach ($products as $product) {
            $skus[] = $product->getSku();
            $positions[] = $product->getPosition();
        }
        return ['skus' => $skus, 'positions' => $positions];
    }

    /**
     * Populate Stock Info
     *
     * @param array $productData
     * @param \Magento\Catalog\Model\Product $product
     * @return void
     */
    public function populateStockInfo(
        array &$productData,
        \Magento\Catalog\Model\Product $product
    ): void {
        $qty = (int) $product->getData("qty");
        $minCartQty = (int) $product->getData("min_sale_qty");
        $productData['stock_info'] = [
            "qty" => $qty ?: null,
            "min_cart_qty" => $minCartQty ?: null
        ];
    }
}
