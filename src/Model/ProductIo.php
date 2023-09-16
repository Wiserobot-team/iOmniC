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
use Magento\Framework\Webapi\Exception as WebapiException;

class ProductIo implements \WiseRobot\Io\Api\ProductIoInterface
{
    /**
     * @var array
     */
    public array $results = [];
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
        AttributeRepositoryInterface $attributeRepositoryInterface
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
        'swatch_image'
    ];

    /**
     * Populate Products by filter params
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
        int $limit = 50
    ): array {
        // create product collection
        $productCollection = $this->productCollectionFactory->create();

        $errorMess = "data request error";

        // store info
        if (!$store) {
            $message = "Field: 'store' is a required field";
            $this->results["error"] = $message;
            throw new WebapiException(__($errorMess), 0, 400, $this->results);
        }
        try {
            $storeInfo = $this->storeManager->getStore($store);
        } catch (\Exception $e) {
            $message = "Requested 'store' " . $store . " doesn't exist";
            $this->results["error"] = $message;
            throw new WebapiException(__($errorMess), 0, 400, $this->results);
        }
        $productCollection->addStoreFilter($store);

        // selecting
        $selectAll = false;
        $selectAttrs = [];
        $select = trim((string) $select);
        if (!$select || $select == "*") {
            $selectAll = true;
            $productCollection->addAttributeToSelect("*");
        } else {
            // default attributes
            $productCollection->addAttributeToSelect($this->defaultAttributes);
            // custom attributes
            $selectAttrs = array_map('trim', explode(",", (string) $select));
            $productCollection->addAttributeToSelect([$selectAttrs]);
        }

        // stock attributes
        $productCollection->joinTable(
            'cataloginventory_stock_item',
            'product_id=entity_id',
            ['qty', 'min_sale_qty'],
            '{{table}}.stock_id = 1',
            'left'
        );

        // filtering
        $filter = trim((string) $filter);
        if ($filter) {
            $filterArray = explode(" and ", (string) $filter);
            foreach ($filterArray as $filterItem) {
                $operator = $this->processFilter($filterItem);
                if (!$operator) {
                    continue;
                }
                $condition = array_map('trim', explode(" " . $operator . " ", (string) $filterItem));
                if (count($condition) != 2) {
                    continue;
                }
                if (!$condition[0] || !$condition[1]) {
                    continue;
                }
                $attrCode = $condition[0];
                $attrValue = $condition[1];
                try {
                    $this->attributeRepositoryInterface->get('catalog_product', $attrCode);
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    $message = "Field: 'filter' - attribute '" . $attrCode . "' doesn't exist";
                    $this->results["error"] = $message;
                    throw new WebapiException(__($errorMess), 0, 400, $this->results);
                }

                if ($operator == "in") {
                    $attrValue = array_map('trim', explode(",", (string) $attrValue));
                }

                $productCollection->addFieldToFilter(
                    $attrCode,
                    [
                        $operator => [$attrValue]
                    ]
                );
            }
        }

        // sorting
        $productCollection->setOrder('entity_id', 'asc');

        // paging
        $total = $productCollection->getSize();
        if (!$page || $page <= 0) {
            $page = 1;
        }
        if (!$limit || $limit <= 0) {
            $limit = 10;
        }
        if ($limit > 50) {
            $limit = 50; // maximum page size
        }

        $result = [];
        $totalPages = ceil($total / $limit);
        if ($page > $totalPages) {
            return $result;
        }

        $productCollection->setPageSize($limit);
        $productCollection->setCurPage($page);
        if ($productCollection->getSize()) {
            foreach ($productCollection as $product) {
                $sku = $product->getData("sku");
                if (!$sku) {
                    continue;
                }
                $productId = (int) $product->getData("entity_id");
                $price = $this->getAttrValue($product, 'price', $store);
                $qty = $product->getData("qty");
                $minCartQty = $product->getData("min_sale_qty");

                // default product data
                $productData = [];
                $attrInfo = 'attribute_info';
                $productData['store_id'] = (int) $storeInfo->getId();
                $productData['store'] = $storeInfo->getName();
                $productData[$attrInfo] = [];
                $productData[$attrInfo]['id'] = $productId;
                $productData[$attrInfo]['sku'] = $sku;
                $productData[$attrInfo]['name'] = $product->getData("name");
                $productData[$attrInfo]['attribute_set'] = $this->getAttrValue($product, 'attribute_set_id', $store);
                $productData[$attrInfo]['visibility'] = $this->getAttrValue($product, 'visibility', $store);
                $productData[$attrInfo]['tax_class'] = $this->getAttrValue($product, 'tax_class_id', $store);
                $productData[$attrInfo]['type_id'] = $product->getData("type_id");
                $productData[$attrInfo]['created_at'] = $product->getData("created_at");
                $productData[$attrInfo]['updated_at'] = $product->getData("updated_at");
                $productData[$attrInfo]['status'] = $this->getAttrValue($product, 'status', $store);
                $productData[$attrInfo]['price'] = ($price) ? floatval($price) : $price;

                // image attributes
                if ($selectAll || in_array("image_attributes", $selectAttrs)) {
                    $productData[$attrInfo]['base_image'] = $this->getImgAttr($product, 'image');
                    $productData[$attrInfo]['base_image_label'] = $this->getImgAttr($product, 'image_label');
                    $productData[$attrInfo]['small_image'] = $this->getImgAttr($product, 'small_image');
                    $productData[$attrInfo]['small_image_label'] = $this->getImgAttr($product, 'small_image_label');
                    $productData[$attrInfo]['thumbnail_image'] = $this->getImgAttr($product, 'thumbnail');
                    $productData[$attrInfo]['thumbnail_image_label'] = $this->getImgAttr($product, 'thumbnail_label');
                    $productData[$attrInfo]['swatch_image'] = $this->getImgAttr($product, 'swatch_image');
                    $productData[$attrInfo]['swatch_image_label'] = '';

                    $additionalImage = $this->populateAdditionalImageInfo($productId, (int) $storeInfo->getId());
                    $productData[$attrInfo]['additional_images'] = $additionalImage['additional_images'];
                    $productData[$attrInfo]['additional_image_labels'] = $additionalImage['additional_image_labels'];
                }

                // product categories
                if ($selectAll || in_array("categories", $selectAttrs)) {
                    $productData[$attrInfo]['categories'] = $this->getCategoryTreeCustom($product, $store);
                }

                if ($selectAll) {
                    $data = $product->getData();
                    if (!is_array($data)) {
                        continue;
                    }
                    foreach ($data as $attrCode => $attrValue) {
                        if (in_array($attrCode, $this->ignoreAttributes)) {
                            continue;
                        }
                        $attrData = $this->getAttrValue($product, $attrCode, $store);
                        if (isset($this->floatAttributes[$attrCode]) && $attrData) {
                            $attrData = floatval($attrData);
                        }
                        $productData[$attrInfo][$attrCode] = $attrData;
                    }
                } else {
                    foreach ($selectAttrs as $attrCode) {
                        // skip attribute doesn't exist
                        if (!$product->getResource()->getAttribute($attrCode) &&
                            !isset($this->customAttributes[$attrCode])) {
                            continue;
                        }
                        $attrData = $this->getAttrValue($product, $attrCode, $store);
                        if (isset($this->floatAttributes[$attrCode]) && $attrData) {
                            $attrData = floatval($attrData);
                        }
                        $productData[$attrInfo][$attrCode] = $attrData;
                    }
                }
                // populate variationInfo
                if ($product->getData("type_id") != "grouped") {
                    $variationInfo = $this->populateVariationInfo($product, $store);
                    $productData['variation_info'] = $variationInfo;
                }

                // populate groupedInfo
                if ($product->getData("type_id") != "configurable") {
                    $groupedInfo = $this->populateGroupedProductInfo($product, $store);
                    $productData['grouped_info'] = $groupedInfo;
                }

                // populate productLinkInfo
                $relatedProducts = $product->getRelatedProducts();
                $relatedSkus = [];
                $relatedPosition = [];
                foreach ($relatedProducts as $relatedProduct) {
                    $relatedSkus[] = $relatedProduct->getSku();
                    $relatedPosition[] = $relatedProduct->getPosition();
                }

                $upSellProducts = $product->getUpSellProducts();
                $upsellSkus = [];
                $upsellPosition = [];
                foreach ($upSellProducts as $upSellProduct) {
                    $upsellSkus[] = $upSellProduct->getSku();
                    $upsellPosition[] = $upSellProduct->getPosition();
                }

                $crossSellProducts = $product->getCrossSellProducts();
                $crosssellSkus = [];
                $crosssellPosition = [];
                foreach ($crossSellProducts as $crossSellProduct) {
                    $crosssellSkus[] = $crossSellProduct->getSku();
                    $crosssellPosition[] = $crossSellProduct->getPosition();
                }

                $productData['product_link_info'] = [
                    "related_skus" => implode(",", $relatedSkus),
                    "related_position" => implode(",", $relatedPosition),
                    "upsell_skus" => implode(",", $upsellSkus),
                    "upsell_position" => implode(",", $upsellPosition),
                    "crosssell_skus" => implode(",", $crosssellSkus),
                    "crosssell_position" => implode(",", $crosssellPosition)
                ];

                // populate stockInfo
                $productData['stock_info'] = [
                    "qty" => ($qty) ? (int) $qty : $qty,
                    "min_cart_qty" => ($minCartQty) ? (int) $minCartQty : $minCartQty
                ];

                // populate imageInfo
                $imageInfo = $this->populateImageInfo($productId, $store);
                if (count($imageInfo)) {
                    $productData['image_info'] = $this->populateImageInfo($productId, $store);
                }
                $result[$sku] = $productData;
            }
            return $result;
        }

        return $result;
    }

    /**
     * Process filter params
     *
     * @param string $string
     * @return string|null
     */
    public function processFilter(string $string): mixed
    {
        switch ($string) {
            case strpos((string) $string, " eq ") == true:
                $operator = "eq";
                break;
            case strpos((string) $string, " gt ") == true:
                $operator = "gt";
                break;
            case strpos((string) $string, " le ") == true:
                $operator = "le";
                break;
            case strpos((string) $string, " in ") == true:
                $operator = "in";
                break;
            default:
                $operator = null;
        }

        return $operator;
    }

    /**
     * Populate Variation Information of Product
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return array
     */
    public function populateVariationInfo(
        \Magento\Catalog\Model\Product $product,
        int $storeId
    ): array {
        // default variationInfo
        $variationInfo = [];
        $variationInfo["is_in_relationship"] = false;
        $variationInfo["is_parent"] = false;
        $variationInfo["parent_sku"] = "";
        $variationInfo["super_attribute"] = "";

        $hasParent = false;
        if ($product->getTypeId() == "configurable") {
            $superAttributes = $this->getRelationshipName($product);
            $variationInfo["is_in_relationship"] = true;
            $variationInfo["is_parent"] = true;
            $variationInfo["parent_sku"] = $product->getSku();
            if ($superAttributes) {
                $variationInfo["super_attribute"] = $superAttributes;
            }
        } elseif ($product->getTypeId() == "simple" || $product->getTypeId() == "virtual") {
            $parentIds = $this->configurableProduct->create()
                ->getParentIdsByChild($product->getId());
            if (count($parentIds)) {
                $hasParent = true;
                $parentId = $parentIds[0];
                $parentProduct = $this->productFactory->create()
                    ->setStoreId($storeId)
                    ->load($parentId);
            }
            if ($hasParent) {
                $variationInfo["is_in_relationship"] = true;
                $variationInfo["is_parent"] = false;
                $variationInfo["parent_sku"] = $parentProduct->getSku();
            }
        }

        $relationshipName = "";
        if ($hasParent && $parentProduct->getTypeId() == "configurable") {
            $relationshipName = $this->getRelationshipName($parentProduct);
        }

        if ($relationshipName && isset($variationInfo["is_parent"]) &&
            isset($variationInfo["parent_sku"]) && $variationInfo["parent_sku"]) {
            $variationInfo["super_attribute"] = $relationshipName;
        }

        return $variationInfo;
    }

    /**
     * Get Configurable Product Attributes
     *
     * @param \Magento\Catalog\Model\Product $parentConfigurableProduct
     * @return string|false
     */
    public function getRelationshipName(
        \Magento\Catalog\Model\Product $parentConfigurableProduct
    ): mixed {
        if ($parentConfigurableProduct->getTypeId() != "configurable") {
            return false;
        }
        $productConfigurableAttrs = [];
        $productAttributeOptions = $this->configurableProduct->create()
            ->getConfigurableAttributesAsArray($parentConfigurableProduct);
        foreach ($productAttributeOptions as $supperAttrOption) {
            $productConfigurableAttrs[] = $supperAttrOption["attribute_code"];
        }
        if (!count($productConfigurableAttrs)) {
            return false;
        }
        sort($productConfigurableAttrs);

        return implode(',', $productConfigurableAttrs);
    }

    /**
     * Populate Grouped Product Information
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return array
     */
    public function populateGroupedProductInfo(
        \Magento\Catalog\Model\Product $product,
        int $storeId
    ): array {
        // default groupedInfo
        $groupedInfo = [];
        $groupedInfo["is_parent"] = false;
        $groupedInfo["parent_sku"] = "";
        $groupedInfo["child_sku"] = "";
        if ($product->getTypeId() == "grouped") {
            $groupedInfo["is_parent"] = true;
            $childrenProductSkus = [];
            $childrenProductIds = $this->groupedProduct->create()
                ->getChildrenIds($product->getId());
            if (isset($childrenProductIds[3]) && count($childrenProductIds[3])) {
                foreach ($childrenProductIds[3] as $childrenProductId) {
                    $childProduct = $this->productFactory->create()
                        ->setStoreId($storeId)->load($childrenProductId);
                    if ($childProduct && $childProduct->getId()) {
                        $childrenProductSkus[] = $childProduct->getSku();
                    }
                }
            }
            if (count($childrenProductSkus)) {
                sort($childrenProductSkus);
                $groupedInfo["child_sku"] = implode(",", $childrenProductSkus);
            }
        } elseif ($product->getTypeId() == "simple" || $product->getTypeId() == "virtual") {
            $parentIds = $this->groupedProduct->create()
                ->getParentIdsByChild($product->getId());
            if (count($parentIds)) {
                $parentId = $parentIds[0];
                $parentProduct = $this->productFactory->create()
                    ->setStoreId($storeId)
                    ->load($parentId);
                $groupedInfo["parent_sku"] = $parentProduct->getSku();
            }
        }

        return $groupedInfo;
    }

    /**
     * Populate Product Image Information
     *
     * @param int $productId
     * @param int $storeId
     * @return array
     */
    public function populateImageInfo(int $productId, int $storeId): array
    {
        $imageInfo = [];
        $product = $this->productFactory->create()
            ->setStoreId($storeId)->load($productId);
        $gallery = $product->getMediaGalleryImages();
        if ($gallery && is_object($gallery) && count($gallery)) {
            $imageData = [];
            foreach ($gallery as $image) {
                $imageData['position'] = $image['position'];
                $imageData['url'] = $image['url'];
                $imageInfo[] = $imageData;
            }
        }

        return $imageInfo;
    }

    /**
     * Populate Additional Product Image Information
     *
     * @param int $productId
     * @param int $storeId
     * @return array
     */
    public function populateAdditionalImageInfo(int $productId, int $storeId): array
    {
        $additionalImage = [];
        $additionalImageLabels = [];
        $product = $this->productFactory->create()
            ->setStoreId($storeId)->load($productId);
        $gallery = $product->getMediaGalleryImages();
        if ($gallery && is_object($gallery) && count($gallery)) {
            foreach ($gallery as $image) {
                $additionalImage[] = $image['file'];
                $additionalImageLabels[] = $image['label'];
            }
        }

        return [
            'additional_images' => implode(',', $additionalImage),
            'additional_image_labels' => implode(',', $additionalImageLabels)
        ];
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
        $store = $this->storeManager->getStore()->load($storeId);
        $attrData = $product->getData($attrCode);
        if ($attrCode == "price") {
            return $product->getPrice();
        }

        if ($attrCode == "special_price") {
            if ($product->getData("special_price")) {
                $now = strtotime((string) $this->timezoneInterface
                    ->date()
                    ->format('Y-m-d H:i:s'));
                $timeMax = strtotime((string) $product->getData("special_to_date"));
                if ($timeMax && $now > $timeMax) {
                    return $product->getPrice();
                } else {
                    if ($product->getTypeId() == "bundle") {
                        return $product->getPrice() * $product->getSpecialPrice() / 100;
                    } else {
                        return $product->getSpecialPrice();
                    }
                }
            } else {
                return $product->getPrice();
            }
        }

        if ($attrCode == "tax_class_id") {
            $taxClassModel = $this->classModelFactory->create()
                ->load($attrData);
            return $taxClassModel->getClassName();
        }

        if ($attrCode == "attribute_set_id") {
            $attributeSets = $this->entityAttributeSetFactory->create()
                ->load($product->getAttributeSetId());
            return $attributeSets->getAttributeSetName();
        }

        if ($attrCode == 'visibility') {
            if ($attrData == 4) {
                $attrData = 'Catalog, Search';
            } elseif ($attrData == 3) {
                $attrData = 'Search';
            } elseif ($attrData == 2) {
                $attrData = 'Catalog';
            } else {
                $attrData = 'Not Visible Individually';
            }
            return $attrData;
        }

        if ($attrCode == 'status') {
            if ($attrData == 2) {
                $attrData = 'Disabled';
            } else {
                $attrData = 'Enabled';
            }
            return $attrData;
        }

        if (isset($this->customAttributes[$attrCode])) {
            if ($attrCode == "category_name") {
                $attrData = $this->getCategoryName($product, $storeId);
            }

            if ($attrCode == "category_tree") {
                $attrData = $this->getCategoryTree($product, $store);
            }

            if ($attrCode == "category_ids") {
                $attrData = implode(",", $product->getCategoryIds());
            }
            return $attrData;
        } else {
            // deal with attribute is select
            if (!$product->getResource()->getAttribute($attrCode)) {
                return null;
            }
            $attrFrontendInput = $product->getResource()
                ->getAttribute($attrCode)
                ->getData("frontend_input");
            if ($attrFrontendInput == "select") {
                if ($product->getData($attrCode)) {
                    $attrData = $product->getResource()->getAttribute($attrCode)
                        ->setStoreId($storeId)
                        ->getFrontend()
                        ->getValue($product);
                } else {
                    $attrData = null;
                }
                return $attrData;
            } elseif ($attrFrontendInput == "multiselect") {
                $attrData = $product->getResource()
                    ->getAttribute($attrCode)
                    ->getFrontend()
                    ->getValue($product);
                return $attrData;
            } elseif ($attrFrontendInput == "media_image") {
                if ($product->getData($attrCode)) {
                    $attrData = $product->getMediaConfig()
                        ->getMediaUrl($product->getData($attrCode));
                    return $attrData;
                }
            }
        }

        return $attrData;
    }

    /**
     * Get Product Image Attributes
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $attrCode
     * @return mixed
     */
    public function getImgAttr(
        \Magento\Catalog\Model\Product $product,
        string $attrCode
    ): mixed {
        return $product->getResource()
            ->getAttribute($attrCode)
            ->getFrontend()
            ->getValue($product);
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
        if (is_array($catIds) && count($catIds)) {
            $catId = end($catIds);
            $cat   = $this->categoryFactory->create()
                ->setStoreId($storeId)
                ->load($catId);
            if ($cat->getId()) {
                if ($cat->getName()) {
                    return $cat->getName();
                }
            }
        }

        return "";
    }

    /**
     * Get Category Tree
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Store\Model\Store $store
     * @return string
     */
    public function getCategoryTree(
        \Magento\Catalog\Model\Product $product,
        \Magento\Store\Model\Store $store
    ): string {
        $j = 0;
        $storeId = $store->getId();
        $categoryIds = $product->getCategoryIds();
        $rootCatId = $store->getRootCategoryId();
        $result = "";
        $arrayCat = [];
        while ($j < count($categoryIds)) {
            $categoryId = $categoryIds[$j];
            if ($categoryId > 0) {
                $category = $this->categoryFactory->create()
                    ->setStoreId($storeId)
                    ->load($categoryId);
                $i = 0;
                $parentCatIds = $category->getParentIds();
                $categoryTree = "";
                if (count($parentCatIds) > 0) {
                    while ($i < count($parentCatIds)) {
                        $parentCatId = $parentCatIds[$i];
                        if ($parentCatId == 1 || $parentCatId == $rootCatId) {
                            ++$i;
                            continue;
                        }
                        $parentCategory = $this->categoryFactory->create()
                            ->setStoreId($storeId)
                            ->load($parentCatId);
                        if ($parentCategory->getName()) {
                            $categoryTree = $categoryTree . $parentCategory->getName() . " > ";
                        }
                        ++$i;
                    }
                }
                if ($category->getName()) {
                    $arrayCat[$category->getId()] = $categoryTree . $category->getName();
                }
                ++$j;
            }
        }

        if (count($arrayCat)) {
            $catIds = array_keys($arrayCat);
            foreach ($catIds as $catId) {
                $category = $this->categoryFactory->create()
                    ->setStoreId($storeId)
                    ->load($catId);

                // remove if exist parent category
                $z = 0;
                $parentCatIds = $category->getParentIds();
                if (count($parentCatIds) > 0) {
                    while ($z < count($parentCatIds)) {
                        $parentCatId = $parentCatIds[$z];
                        if (in_array($parentCatId, $catIds)) {
                            unset($arrayCat[$parentCatId]);
                        }
                        ++$z;
                    }
                }
            }
            foreach ($arrayCat as $key => $value) {
                $result = $result . $value . " : ";
            }
        }

        return trim((string) $result, " : ");
    }

    /**
     * Get Custom Category Tree
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return string
     */
    public function getCategoryTreeCustom(
        \Magento\Catalog\Model\Product $product,
        int $storeId
    ): string {
        $j = 0;
        $categoryIds = $product->getCategoryIds();
        $result = "";
        $arrayCat = [];
        while ($j < count($categoryIds)) {
            $categoryId = $categoryIds[$j];
            if ($categoryId > 0) {
                $category = $this->categoryFactory->create()
                    ->setStoreId($storeId)
                    ->load($categoryId);
                $i = 0;
                $parentCatIds = $category->getParentIds();
                $categoryTree = "";
                if (count($parentCatIds) > 0) {
                    while ($i < count($parentCatIds)) {
                        $parentCatId = $parentCatIds[$i];
                        if ($parentCatId == 1) {
                            ++$i;
                            continue;
                        }
                        $parentCategory = $this->categoryFactory->create()
                            ->setStoreId($storeId)
                            ->load($parentCatId);
                        if ($parentCategory->getName()) {
                            $categoryTree = $categoryTree . $parentCategory->getName() . "/";
                        }
                        ++$i;
                    }
                }
                if ($category->getName()) {
                    $arrayCat[$category->getId()] = $categoryTree . $category->getName();
                }
                ++$j;
            }
        }

        if (count($arrayCat)) {
            $catIds = array_keys($arrayCat);
            foreach ($catIds as $catId) {
                $category = $this->categoryFactory->create()
                    ->setStoreId($storeId)
                    ->load($catId);

                // remove if exist parent category
                $parentCatIds = $category->getParentIds();
                $z            = 0;
                if (count($parentCatIds) > 0) {
                    while ($z < count($parentCatIds)) {
                        $parentCatId = $parentCatIds[$z];
                        if (in_array($parentCatId, $catIds)) {
                            unset($arrayCat[$parentCatId]);
                        }
                        ++$z;
                    }
                }
            }
            foreach ($arrayCat as $key => $value) {
                $result = $result . $value . ",";
            }
        }

        return trim((string) $result, ",");
    }
}
