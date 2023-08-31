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
namespace Wiserobot\Io\Model;

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

class ProductIo implements \Wiserobot\Io\Api\ProductIoInterface
{
    public $results = [];
    public $productFactory;
    public $productCollectionFactory;
    public $storeManager;
    public $timezoneInterface;
    public $classModelFactory;
    public $entityAttributeSetFactory;
    public $categoryFactory;
    public $configurableProduct;
    public $groupedProduct;
    public $attributeRepositoryInterface;

    public function __construct(
        ProductFactory                      $productFactory,
        ProductCollectionFactory            $productCollectionFactory,
        StoreManagerInterface               $storeManager,
        TimezoneInterface                   $timezoneInterface,
        ClassModelFactory                   $classModelFactory,
        EntityAttributeSetFactory           $entityAttributeSetFactory,
        CategoryFactory                     $categoryFactory,
        ConfigurableProduct                 $configurableProduct,
        GroupedProduct                      $groupedProduct,
        AttributeRepositoryInterface        $attributeRepositoryInterface

    ) {
        $this->productFactory               = $productFactory;
        $this->productCollectionFactory     = $productCollectionFactory;
        $this->storeManager                 = $storeManager;
        $this->timezoneInterface            = $timezoneInterface;
        $this->classModelFactory            = $classModelFactory;
        $this->entityAttributeSetFactory    = $entityAttributeSetFactory;
        $this->categoryFactory              = $categoryFactory;
        $this->configurableProduct          = $configurableProduct;
        $this->groupedProduct               = $groupedProduct;
        $this->attributeRepositoryInterface = $attributeRepositoryInterface;
    }

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

    public $customAttributes = [
        "category_name" => "Category Name",
        "category_tree" => "Category Tree",
        "category_ids"  => "Category Ids"
    ];

    public $floatAttributes = [
        "price"         => "Price",
        "special_price" => "Special Price",
        "tier_price"    => "Tier Price",
        "cost"          => "Cost",
        "weight"        => "Weight"
    ];

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

    public function products($store, $select = "*", $filter = "", $page = 1, $limit = 50)
    {
        // create product collection
        $productCollection = $this->productCollectionFactory->create();

        // store info
        if (!$store) {
            $this->results["error"] = "Field: 'store' is a required field";
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results);
        }
        try {
            $storeInfo = $this->storeManager->getStore($store);
        } catch (\Exception $e) {
            $this->results["error"] = "Requested 'store' " . $store . " doesn't exist";
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results);
        }
        $productCollection->addStoreFilter($store);

        // selecting
        $selectAll   = false;
        $selectAttrs = [];
        $select      = trim((string) $select);
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
        $productCollection->joinTable('cataloginventory_stock_item', 'product_id=entity_id', ['qty', 'min_sale_qty'], '{{table}}.stock_id = 1', 'left');

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
                $attrCode  = $condition[0];
                $attrValue = $condition[1];
                try {
                   $this->attributeRepositoryInterface->get('catalog_product', $attrCode);
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    $this->results["error"] = "Field: 'filter' - attribute '" . $attrCode . "' doesn't exist";
                    throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results);
                }

                if ($operator == "in") {
                    $attrValue = array_map('trim', explode(",", (string) $attrValue));
                }

                $productCollection->addFieldToFilter(
                    $attrCode,
                    [$operator => [$attrValue]]
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
        $totalPages = ceil($total / $limit);
        if ($page > $totalPages) {
            return;
        }

        $productCollection->setPageSize($limit);
        $productCollection->setCurPage($page);

        $result = [];
        if ($productCollection->getSize()) {
            foreach ($productCollection as $product) {
                $sku = $product->getData("sku");
                if (!$sku) {
                    continue;
                }
                $productId  = $product->getData("entity_id");
                $price      = $this->getAttributeValue($product, 'price', $store);
                $qty        = $product->getData("qty");
                $minCartQty = $product->getData("min_sale_qty");

                // default product data
                $productData                                        = [];
                $productData['store_id']                            = $storeInfo->getId();
                $productData['store']                               = $storeInfo->getName();
                $productData['attribute_info']                      = [];
                $productData['attribute_info']['id']                = $productId;
                $productData['attribute_info']['sku']               = $sku;
                $productData['attribute_info']['name']              = $product->getData("name");
                $productData['attribute_info']['attribute_set']     = $this->getAttributeValue($product, 'attribute_set_id', $store);
                $productData['attribute_info']['visibility']        = $this->getAttributeValue($product, 'visibility', $store);
                $productData['attribute_info']['tax_class']         = $this->getAttributeValue($product, 'tax_class_id', $store);
                $productData['attribute_info']['type_id']           = $product->getData("type_id");
                $productData['attribute_info']['created_at']        = $product->getData("created_at");
                $productData['attribute_info']['updated_at']        = $product->getData("updated_at");
                $productData['attribute_info']['status']            = $this->getAttributeValue($product, 'status', $store);
                $productData['attribute_info']['price']             = ($price) ? floatval($price) : $price;

                // image attributes
                if ($selectAll || in_array("image_attributes", $selectAttrs)) {
                    $productData['attribute_info']['base_image']              = $product->getResource()->getAttribute('image')->getFrontend()->getValue($product);
                    $productData['attribute_info']['base_image_label']        = $product->getResource()->getAttribute('image_label')->getFrontend()->getValue($product);
                    $productData['attribute_info']['small_image']             = $product->getResource()->getAttribute('small_image')->getFrontend()->getValue($product);
                    $productData['attribute_info']['small_image_label']       = $product->getResource()->getAttribute('small_image_label')->getFrontend()->getValue($product);
                    $productData['attribute_info']['thumbnail_image']         = $product->getResource()->getAttribute('thumbnail')->getFrontend()->getValue($product);
                    $productData['attribute_info']['thumbnail_image_label']   = $product->getResource()->getAttribute('thumbnail_label')->getFrontend()->getValue($product);
                    $productData['attribute_info']['swatch_image']            = $product->getResource()->getAttribute('swatch_image')->getFrontend()->getValue($product);
                    $productData['attribute_info']['swatch_image_label']      = '';

                    $additionalImage = $this->populateAdditionalImageInfo($productId, $storeInfo->getId());
                    $productData['attribute_info']['additional_images']       = $additionalImage['additional_images'];
                    $productData['attribute_info']['additional_image_labels'] = $additionalImage['additional_image_labels'];
                }

                // product categories
                if ($selectAll || in_array("categories", $selectAttrs)) {
                    $productData['attribute_info']['categories'] = $this->getCategoryTreeCustom($product, $storeInfo);
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
                        $attrData = $this->getAttributeValue($product, $attrCode, $store);
                        if (isset($this->floatAttributes[$attrCode]) && $attrData) {
                            $attrData = floatval($attrData);
                        }
                        $productData['attribute_info'][$attrCode] = $attrData;
                    }
                } else {
                    foreach ($selectAttrs as $attrCode) {
                        // skip attribute doesn't exist
                        if (!$product->getResource()->getAttribute($attrCode)) {
                            if (!isset($this->customAttributes[$attrCode])) {
                                continue;
                            }
                        }
                        $attrData = $this->getAttributeValue($product, $attrCode, $store);
                        if (isset($this->floatAttributes[$attrCode]) && $attrData) {
                            $attrData = floatval($attrData);
                        }
                        $productData['attribute_info'][$attrCode] = $attrData;
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
                $relatedSkus     = [];
                $relatedPosition = [];
                foreach ($relatedProducts as $relatedProduct) {
                    $relatedSkus[]     = $relatedProduct->getSku();
                    $relatedPosition[] = $relatedProduct->getPosition();
                }

                $upSellProducts = $product->getUpSellProducts();
                $upsellSkus     = [];
                $upsellPosition = [];
                foreach ($upSellProducts as $upSellProduct) {
                    $upsellSkus[]     = $upSellProduct->getSku();
                    $upsellPosition[] = $upSellProduct->getPosition();
                }

                $crossSellProducts = $product->getCrossSellProducts();
                $crosssellSkus     = [];
                $crosssellPosition = [];
                foreach ($crossSellProducts as $crossSellProduct) {
                    $crosssellSkus[]     = $crossSellProduct->getSku();
                    $crosssellPosition[] = $crossSellProduct->getPosition();
                }

                $productData['product_link_info'] = [
                    "related_skus"       => implode("," , $relatedSkus),
                    "related_position"   => implode("," , $relatedPosition),
                    "upsell_skus"        => implode("," , $upsellSkus),
                    "upsell_position"    => implode("," , $upsellPosition),
                    "crosssell_skus"     => implode("," , $crosssellSkus),
                    "crosssell_position" => implode("," , $crosssellPosition)
                ];

                // populate stockInfo
                $productData['stock_info'] = [
                    "qty"          => ($qty) ? intval($qty) : $qty,
                    "min_cart_qty" => ($minCartQty) ? intval($minCartQty) : $minCartQty
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

    public function processFilter($string)
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

    public function populateVariationInfo($product, $storeId)
    {
        // default variationInfo
        $variationInfo = [];
        $variationInfo["is_in_relationship"] = false;
        $variationInfo["is_parent"]          = false;
        $variationInfo["parent_sku"]         = "";
        $variationInfo["super_attribute"]    = "";

        $hasParent = false;
        if ($product->getTypeId() == "configurable") {
            $superAttributes = $this->getRelationshipName($product);
            $variationInfo["is_in_relationship"]  = true;
            $variationInfo["is_parent"]           = true;
            $variationInfo["parent_sku"]          = $product->getSku();
            if ($superAttributes) {
                $variationInfo["super_attribute"] = $superAttributes;
            }
        } elseif ($product->getTypeId() == "simple" || $product->getTypeId() == "virtual") {
            $parentIds = $this->configurableProduct->create()->getParentIdsByChild($product->getId());
            if (count($parentIds)) {
                $hasParent     = true;
                $parentId      = $parentIds[0];
                $parentProduct = $this->productFactory->create()
                                    ->setStoreId($storeId)
                                    ->load($parentId);
            }
            if ($hasParent) {
                $variationInfo["is_in_relationship"] = true;
                $variationInfo["is_parent"]          = false;
                $variationInfo["parent_sku"]         = $parentProduct->getSku();
            }
        }

        $relationshipName = "";
        if ($hasParent && $parentProduct->getTypeId() == "configurable") {
            $relationshipName = $this->getRelationshipName($parentProduct);
        }

        if ($relationshipName && isset($variationInfo["is_parent"]) && isset($variationInfo["parent_sku"]) && $variationInfo["parent_sku"]) {
            $variationInfo["super_attribute"] = $relationshipName;
        }

        return $variationInfo;
    }

    public function getRelationshipName($parentConfigurableProduct)
    {
        if ($parentConfigurableProduct->getTypeId() != "configurable") {
            return;
        }

        $productConfigurableAttrs = [];
        $productAttributeOptions  = $this->configurableProduct->create()->getConfigurableAttributesAsArray($parentConfigurableProduct);
        foreach ($productAttributeOptions as $supperAttrOption) {
            $productConfigurableAttrs[] = $supperAttrOption["attribute_code"];
        }
        if (!count($productConfigurableAttrs)) {
            return;
        }
        sort($productConfigurableAttrs);

        return implode(',', $productConfigurableAttrs);
    }

    public function populateGroupedProductInfo($product, $storeId)
    {
        // default groupedInfo
        $groupedInfo = [];
        $groupedInfo["is_parent"]  = false;
        $groupedInfo["parent_sku"] = "";
        $groupedInfo["child_sku"]  = "";

        if ($product->getTypeId() == "grouped") {
            $groupedInfo["is_parent"] = true;
            $childrenProductSkus      = [];
            $childrenProductIds       = $this->groupedProduct->create()->getChildrenIds($product->getId());
            if (isset($childrenProductIds[3]) && count($childrenProductIds[3])) {
                foreach ($childrenProductIds[3] as $childrenProductId) {
                    $childProduct = $this->productFactory->create()->setStoreId($storeId)->load($childrenProductId);
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
            $parentIds = $this->groupedProduct->create()->getParentIdsByChild($product->getId());
            if (count($parentIds)) {
                $parentId      = $parentIds[0];
                $parentProduct = $this->productFactory->create()
                    ->setStoreId($storeId)
                    ->load($parentId);
                $groupedInfo["parent_sku"] = $parentProduct->getSku();
            }
        }

        return $groupedInfo;
    }

    public function populateImageInfo($productId, $storeId)
    {
        $imageInfo = [];
        $product   = $this->productFactory->create()->setStoreId($storeId)->load($productId);
        $gallery   = $product->getMediaGalleryImages();
        if ($gallery && is_object($gallery) && count($gallery)) {
            $imageData = [];
            foreach ($gallery as $image) {
                $imageData['position'] = $image['position'];
                $imageData['url']      = $image['url'];
                $imageInfo[] = $imageData;
            }
        }

        return $imageInfo;
    }

    public function populateAdditionalImageInfo($productId, $storeId)
    {
        $additionalImage       = [];
        $additionalImageLabels = [];
        $product               = $this->productFactory->create()->setStoreId($storeId)->load($productId);
        $gallery               = $product->getMediaGalleryImages();
        if ($gallery && is_object($gallery) && count($gallery)) {
            foreach ($gallery as $image) {
                $additionalImage[]       = $image['file'];
                $additionalImageLabels[] = $image['label'];
            }
        }

        return [
            'additional_images'       => implode(',', $additionalImage),
            'additional_image_labels' => implode(',', $additionalImageLabels)
        ];
    }

    public function getImageLabel($productId, $storeId, $imageAttribute)
    {
        $imageLabel = '';
        $product    = $this->productFactory->create()->setStoreId($storeId)->load($productId);
        $gallery    = $product->getData('media_gallery');
        $file       = $product->getData($imageAttribute);
        if ($file && $gallery && array_key_exists('images', $gallery)) {
            foreach ($gallery['images'] as $image) {
                if ($image['file'] == $file)
                    $imageLabel = $image['label'];
            }
        }

        return $imageLabel;
    }

    public function getAttributeValue($product, $attrCode, $storeId)
    {
        $store    = $this->storeManager->getStore()->load($storeId);
        $attrData = $product->getData($attrCode);
        if ($attrCode == "price") {
            return $product->getPrice();
        }

        if ($attrCode == "special_price") {
            if ($product->getData("special_price")) {
                $now     = strtotime((string) $this->timezoneInterface->date()->format(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT));
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
            $taxClassModel = $this->classModelFactory->create()->load($attrData);
            return $taxClassModel->getClassName();
        }

        if ($attrCode == "attribute_set_id") {
            $attributeSets = $this->entityAttributeSetFactory->create()->load($product->getAttributeSetId());
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
                $attrData = $this->getCategoryName($product, $store);
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
                return;
            }
            $attrFrontendInput = $product->getResource()->getAttribute($attrCode)->getData("frontend_input");
            if ($attrFrontendInput == "select") {
                if ($product->getData($attrCode)) {
                    $attrData = $product->getResource()->getAttribute($attrCode)->setStoreId($storeId)->getFrontend()->getValue($product);
                } else {
                    $attrData = null;
                }
                return $attrData;
            } elseif ($attrFrontendInput == "multiselect") {
                $attrData = $product->getResource()->getAttribute($attrCode)->getFrontend()->getValue($product);
                return $attrData;
            } elseif ($attrFrontendInput == "media_image") {
                if ($product->getData($attrCode)) {
                    $attrData = $product->getMediaConfig()->getMediaUrl($product->getData($attrCode));
                    return $attrData;
                }
            }
        }

        return $attrData;
    }

    public function getCategoryName($product, $store)
    {
        $catIds = $product->getCategoryIds();
        if (is_array($catIds) && count($catIds)) {
            $catId = end($catIds);
            $cat   = $this->categoryFactory->create()
                        ->setStoreId($store->getId())
                        ->load($catId);
            if ($cat->getId()) {
                if ($cat->getName()) {
                    return $cat->getName();
                }
            }
        }

        return "";
    }

    public function getCategoryTree($product, $store)
    {
        $storeId     = $store->getId();
        $categoryIds = $product->getCategoryIds();
        $rootCatId   = $store->getRootCategoryId();
        $j           = 0;
        $result      = "";
        $arrayCat    = [];
        while ($j < count($categoryIds)) {
            $categoryId = $categoryIds[$j];
            if ($categoryId > 0) {
                $category     = $this->categoryFactory->create()
                                    ->setStoreId($storeId)
                                    ->load($categoryId);
                $parentCatIds = $category->getParentIds();
                $i            = 0;
                $categoryTree = "";
                if (count($parentCatIds) > 0) {
                    while ($i < count($parentCatIds)) {
                        $parentCatId = $parentCatIds[$i];
                        if ($parentCatId == 1 || $parentCatId == $rootCatId) {
                            ++$i;
                            continue;
                        }
                        $parentCatetory = $this->categoryFactory->create()
                                            ->setStoreId($storeId)
                                            ->load($parentCatId);
                        if ($parentCatetory->getName()) {
                            $categoryTree = $categoryTree . $parentCatetory->getName() . " > ";
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
                $result = $result . $value . " : ";
            }
        }

        return trim((string) $result, " : ");
    }

    public function getCategoryTreeCustom($product, $store)
    {
        $storeId     = $store->getId();
        $categoryIds = $product->getCategoryIds();
        $rootCatId   = $store->getRootCategoryId();
        $j           = 0;
        $result      = "";
        $arrayCat    = [];
        while ($j < count($categoryIds)) {
            $categoryId = $categoryIds[$j];
            if ($categoryId > 0) {
                $category     = $this->categoryFactory->create()
                                    ->setStoreId($storeId)
                                    ->load($categoryId);
                $parentCatIds = $category->getParentIds();
                $i            = 0;
                $categoryTree = "";
                if (count($parentCatIds) > 0) {
                    while ($i < count($parentCatIds)) {
                        $parentCatId = $parentCatIds[$i];
                        if ($parentCatId == 1) {
                            ++$i;
                            continue;
                        }
                        $parentCatetory = $this->categoryFactory->create()
                                            ->setStoreId($storeId)
                                            ->load($parentCatId);
                        if ($parentCatetory->getName()) {
                            $categoryTree = $categoryTree . $parentCatetory->getName() . "/";
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
