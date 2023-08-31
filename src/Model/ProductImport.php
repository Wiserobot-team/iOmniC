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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Magento\ConfigurableProduct\Model\Product\Type\ConfigurableFactory as ConfigurableProduct;
use Magento\GroupedProduct\Model\Product\Type\GroupedFactory as GroupedProduct;
use Magento\Tax\Model\ClassModelFactory;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory as ConfigurableOptionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\ProductAttributeManagementInterface as ProductAttributeManagement;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Eav\Model\EntityFactory;
use Magento\Eav\Model\Entity\AttributeFactory as EntityAttributeFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as EntityAttributeSetFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Eav\Api\AttributeRepositoryInterface;

use Wiserobot\Io\Helper\ProductAttribute as ProductAttributeHelper;
use Wiserobot\Io\Helper\Category as CategoryHelper;
use Wiserobot\Io\Helper\Image as ImageHelper;
use Wiserobot\Io\Model\ProductimageFactory;

class ProductImport implements \Wiserobot\Io\Api\ProductImportInterface
{
    private $logFile     = "wiserobotio_product_import.log";
    private $showLog     = false;
    public $isNewProduct = false;
    public $results      = [];
    public $scopeConfig;
    public $filesystem;
    public $storeManager;
    public $configurableProduct;
    public $groupedProduct;
    public $classModelFactory;
    public $productLink;
    public $productAttributeRepository;
    public $categoryLinkManagementInterface;
    public $productUrlRewriteGenerator;
    public $urlPersistInterface;
    public $productOptionFactory;
    public $productRepositoryInterface;
    public $productAttributeManagement;
    public $productFactory;
    public $stockRegistryInterface;
    public $productCollectionFactory;
    public $entityFactory;
    public $entityAttributeFactory;
    public $entityAttributeSetFactory;
    public $resourceConnection;
    public $timezoneInterface;
    public $productResource;
    public $categoryFactory;
    public $attributeRepositoryInterface;
    public $productAttributeHelper;
    public $categoryHelper;
    public $imageHelper;
    public $productimageFactory;

    public function __construct(
        ScopeConfigInterface                            $scopeConfig,
        Filesystem                                      $filesystem,
        StoreManagerInterface                           $storeManager,
        ConfigurableProduct                             $configurableProduct,
        GroupedProduct                                  $groupedProduct,
        ClassModelFactory                               $classModelFactory,
        ProductLinkInterfaceFactory                     $productLink,
        AttributeRepository                             $productAttributeRepository,
        CategoryLinkManagementInterface                 $categoryLinkManagementInterface,
        ProductUrlRewriteGenerator                      $productUrlRewriteGenerator,
        UrlPersistInterface                             $urlPersistInterface,
        ConfigurableOptionFactory                       $productOptionFactory,
        ProductRepositoryInterface                      $productRepositoryInterface,
        ProductAttributeManagement                      $productAttributeManagement,
        ProductFactory                                  $productFactory,
        StockRegistryInterface                          $stockRegistryInterface,
        ProductCollectionFactory                        $productCollectionFactory,
        EntityFactory                                   $entityFactory,
        EntityAttributeFactory                          $entityAttributeFactory,
        EntityAttributeSetFactory                       $entityAttributeSetFactory,
        ResourceConnection                              $resourceConnection,
        TimezoneInterface                               $timezoneInterface,
        ProductResource                                 $productResource,
        CategoryFactory                                 $categoryFactory,
        AttributeRepositoryInterface                    $attributeRepositoryInterface,
        ProductAttributeHelper                          $productAttributeHelper,
        CategoryHelper                                  $categoryHelper,
        ImageHelper                                     $imageHelper,
        ProductimageFactory                             $productimageFactory

    ) {
        $this->scopeConfig                              = $scopeConfig;
        $this->filesystem                               = $filesystem;
        $this->storeManager                             = $storeManager;
        $this->configurableProduct                      = $configurableProduct;
        $this->groupedProduct                           = $groupedProduct;
        $this->classModelFactory                        = $classModelFactory;
        $this->productLink                              = $productLink;
        $this->productAttributeRepository               = $productAttributeRepository;
        $this->categoryLinkManagementInterface          = $categoryLinkManagementInterface;
        $this->productUrlRewriteGenerator               = $productUrlRewriteGenerator;
        $this->urlPersistInterface                      = $urlPersistInterface;
        $this->productOptionFactory                     = $productOptionFactory;
        $this->productRepositoryInterface               = $productRepositoryInterface;
        $this->productAttributeManagement               = $productAttributeManagement;
        $this->productFactory                           = $productFactory;
        $this->stockRegistryInterface                   = $stockRegistryInterface;
        $this->productCollectionFactory                 = $productCollectionFactory;
        $this->entityFactory                            = $entityFactory;
        $this->entityAttributeFactory                   = $entityAttributeFactory;
        $this->entityAttributeSetFactory                = $entityAttributeSetFactory;
        $this->resourceConnection                       = $resourceConnection;
        $this->timezoneInterface                        = $timezoneInterface;
        $this->productResource                          = $productResource;
        $this->categoryFactory                          = $categoryFactory;
        $this->attributeRepositoryInterface             = $attributeRepositoryInterface;
        $this->productAttributeHelper                   = $productAttributeHelper;
        $this->productAttributeHelper->productAttribute = $this;
        $this->categoryHelper                           = $categoryHelper;
        $this->categoryHelper->logModel                 = $this;
        $this->imageHelper                              = $imageHelper;
        $this->productimageFactory                      = $productimageFactory;
    }

    public $ignoreAttributes = [
        'base_image',
        'base_image_label',
        'small_image',
        'small_image_label',
        'thumbnail_image',
        'thumbnail_image_label',
        'swatch_image',
        'swatch_image_label',
        'additional_images',
        'additional_image_labels'
    ];

    public function import($store, $attribute_info, $variation_info, $grouped_info = [], $stock_info = [], $image_info = [])
    {
        // response messages
        $this->results["response"]["data"]["success"]      = [];
        $this->results["response"]["data"]["error"]        = [];
        $this->results["response"]["quantity"]["success"]  = [];
        $this->results["response"]["quantity"]["error"]    = [];
        $this->results["response"]["category"]["success"]  = [];
        $this->results["response"]["category"]["error"]    = [];
        $this->results["response"]["image"]["success"]     = [];
        $this->results["response"]["image"]["error"]       = [];
        $this->results["response"]["variation"]["success"] = [];
        $this->results["response"]["variation"]["error"]   = [];

        // store info
        if (!$store) {
            $this->results["response"]["data"]["error"][] = "Field: 'store' is a required field";
            $this->log("ERROR: Field: 'store' is a required field");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        try {
            $storeInfo = $this->storeManager->getStore($store);
        } catch (\Exception $e) {
            $this->results["response"]["data"]["error"][] = "Requested 'store' " . $store . " doesn't exist";
            $this->log("ERROR: Requested 'store' " . $store . " doesn't exist");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }

        // attribute info
        if (!$attribute_info) {
            $this->results["response"]["data"]["error"][] = "Field: 'attribute_info' is a required field";
            $this->log("ERROR: Field: 'attribute_info' is a required field");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        if (!isset($attribute_info["sku"])) {
            $this->results["response"]["data"]["error"][] = "Field: 'attribute_info' - 'sku' data is a required";
            $this->log("ERROR: Field: 'attribute_info' - 'sku' data is a required");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        if (!isset($attribute_info["gtin"])) {
            $this->results["response"]["data"]["error"][] = "Field: 'attribute_info' - 'gtin' data is a required";
            $this->log("ERROR: Field: 'attribute_info' - 'gtin' data is a required");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }

        // variation info
        if (!$variation_info) {
            $this->results["response"]["data"]["error"][] = "Field: 'variation_info' is a required field";
            $this->log("ERROR: Field: 'variation_info' is a required field");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        if (!isset($variation_info["is_in_relationship"]) || !isset($variation_info["is_parent"]) || !isset($variation_info["parent_sku"]) || !isset($variation_info["super_attribute"])) {
            $this->results["response"]["data"]["error"][] = "Field: 'attribute_info' - data error";
            $this->log("ERROR: Field: 'attribute_info' - data error");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }

        $sku              = $attribute_info["sku"];
        $productId        = $this->productFactory->create()->getIdBySku($sku);
        $defaultAttrSetId = $this->productResource->getEntityType()->getDefaultAttributeSetId();
        $attributeSetId   = "";
        if (!empty($attribute_info['attribute_set'])) {
            $attributeSetName = $attribute_info['attribute_set'];
            $entityTypeId     = $this->entityFactory
                                    ->create()
                                    ->setType('catalog_product')
                                    ->getTypeId();
            $attributeSetId   = $this->entityAttributeSetFactory
                                    ->create()
                                    ->getCollection()
                                    ->setEntityTypeFilter($entityTypeId)
                                    ->addFieldToFilter('attribute_set_name', $attributeSetName)
                                    ->getFirstItem()
                                    ->getAttributeSetId();
            if (!$attributeSetId) {
                $this->results["response"]["data"]["error"][] = "error sku '" . $sku . "' requested 'attribute_set': '" . $attribute_info['attribute_set'] . "' doesn't exist";
                $this->log("ERROR: sku '" . $sku . "' requested 'attribute_set': '" . $attribute_info['attribute_set'] . "' doesn't exist");
                $this->cleanResponseMessages();
                throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
            }
        }

        $createParent = false;
        if (!$productId) {
            $this->isNewProduct = true;
            $product = $this->productFactory->create();

            $ioProductImages = $this->productimageFactory->create()
                                    ->getCollection()
                                    ->addFieldToFilter('sku', $sku);
            foreach ($ioProductImages as $ioProductImage) {
                $ioProductImage->delete();
            }

            $defaultDataToImport['default'] = [];

            if ($store != 0) {
                $websiteId = $storeInfo->getData('website_id');
                $product->setWebsiteIds([$websiteId]);
            }

            $product->setData('sku', $sku);
            $defaultDataToImport['default']['sku'] = $sku;

            /* code added by ktpl */
            $product->setData('gtin', $attribute_info["gtin"]);
            $defaultDataToImport['default']['gtin'] = $attribute_info["gtin"];
            /* code added by ktpl */

            if ($attributeSetId) {
                $product->setData('attribute_set_id', $attributeSetId);
                $defaultDataToImport['default']['attribute_set'] = $attributeSetId;
            } else {
                $product->setData('attribute_set_id', $defaultAttrSetId);
                $defaultDataToImport['default']['attribute_set'] = $defaultAttrSetId;
            }

            if ($variation_info['is_parent']) {
                $createParent = true;
                $product->setData('type_id', 'configurable');
                $defaultDataToImport['default']['type_id'] = 'configurable';
            }

            if (count($grouped_info)) {
                $createParent = true;
                $product->setData('type_id', 'grouped');
                $defaultDataToImport['default']['type_id'] = 'grouped';
            }

            $defaultVisibility = 4; // Catalog, Search
            $product->setData('visibility', $defaultVisibility);
            $defaultDataToImport['default']['visibility'] = $defaultVisibility;

            if ($variation_info['is_in_relationship'] && !$variation_info['is_parent']) {
                // set visibility for child product
                $defaultChildVisibility = 1; // Not Visible Individually;
                $product->setData('visibility', $defaultChildVisibility);
                $defaultDataToImport['default']['visibility'] = $defaultChildVisibility;
            }

            if (!$createParent) {
                $product->setData('type_id', 'simple');
                $defaultDataToImport['default']['type_id'] = 'simple';
            }

            $defaultStatus = 1; // Enabled
            $product->setData('status', $defaultStatus);
            $defaultDataToImport['default']['status'] = $defaultStatus;

            $defaultTaxClass = 0; // None
            $product->setData('tax_class_id', $defaultTaxClass);
            $defaultDataToImport['default']['tax_class'] = $defaultTaxClass;

            $product->setStockData([
                "qty"         => 0,
                "is_in_stock" => 1
            ]);
        } else {
            $product = $this->productFactory->create()
                            ->setStoreId($store)
                            ->load($productId);
            if (!$product->getId()) {
                $this->results["response"]["data"]["error"][] = "error sku '" . $sku . "' can't load product";
                $this->log("ERROR: sku '" . $sku . "' can't load product");
                $this->cleanResponseMessages();
                throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
            }
        }

        $productDataToImport = $this->getDataToImport($attribute_info, $product, $variation_info, $grouped_info, $stock_info, $image_info, $store);
        if ($this->isNewProduct) {
            $productDataToImport = array_merge($productDataToImport, $defaultDataToImport);
        }

        try {
            $this->importData($product, $productDataToImport, $store);
            $this->cleanResponseMessages();
            return $this->results;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Webapi\Exception(__("product import error"), 0, 400, $this->results["response"]);
        }
    }

    public function importData($product, $productDataToImport, $storeId)
    {
        $sku = $product->getSku();

        try {
            $importedAttributes = [];
            if (count($productDataToImport['attributes'])) {
                foreach ($productDataToImport['attributes'] as $attrCode => $attrValue) {
                    if ($attrCode == "url_key" && !$attrValue) {
                        // prevent set url_key if it doesn't have value
                        continue;
                    }

                    if (in_array($attrCode, ['_related_skus', '_upsell_skus', '_crosssell_skus'])) {
                        continue;
                    }

                    if (in_array($attrCode, $this->ignoreAttributes)) {
                        continue;
                    }

                    if (!in_array($attrCode, ['attribute_set', 'visibility', 'tax_class', 'status'])) {
                        // some case doesn't treat like select
                        $attribute = $this->getAttribute($attrCode);
                        if ($attribute && $attribute->getData("frontend_input") == "select") {
                            $attrValue = $this->getAttributeValue($attrCode, $attrValue);
                            if (!$attrValue) {
                                // TODO: find way to set empty value for dropdown
                                continue;
                            }
                        }
                    }

                    if ($attrCode == 'attribute_set') {
                        $attrCode = 'attribute_set_id';
                    }

                    if ($attrCode == 'tax_class') {
                        $attrCode = 'tax_class_id';
                    }

                    if ($product->getData($attrCode) != $attrValue) {
                        $product->setData($attrCode, $attrValue);
                        // use saveAttribute for existing product only
                        if (!$this->isNewProduct) {
                            $this->productResource->saveAttribute($product, $attrCode);
                        }
                        $importedAttributes[$attrCode] = $attrValue;
                    }
                }
            }

            // create a new product
            if ($this->isNewProduct) {
                $message = "REQUEST: sku '" . $sku . "' " . json_encode(array_merge($productDataToImport['default'], $importedAttributes));
                $this->log($message);
                // $this->productRepositoryInterface->save($product);
                $product->save();
                try {
                    $this->urlPersistInterface
                         ->replace($this->productUrlRewriteGenerator->generate($product));
                } catch (\Exception $e) {
                    // try to generate url
                }
                $this->results["response"]["data"]["success"][] = "saved sku '" . $sku . "' - product id <" . $product->getId() . "> saved successful";
                $this->log("SAVED: sku '" . $sku . "' - product id <" . $product->getId() . "> saved successful");
            } else {
                if (count($importedAttributes)) {
                    $message = "REQUEST: sku '" . $sku . "' - product id <" . $product->getId() . ">" . json_encode($importedAttributes);
                    $this->log($message);
                    // change product update at after use saveAttribute
                    $this->updateAtProductAfterSaveAttribute($product->getId());
                    $this->results["response"]["data"]["success"][] = "saved sku '" . $sku . "' - product id <" . $product->getId() . "> saved successful";
                    $this->log("SAVED: sku '" . $sku . "' - product id <" . $product->getId() . "> saved successful");
                } else {
                    $this->results["response"]["data"]["success"][] = "skip sku '" . $sku . "' - product id <" . $product->getId() . "> no data changed";
                    $this->log("SKIP: sku '" . $sku . "' - product id <" . $product->getId() . "> no data changed");
                }
            }
        } catch (\Exception $e) {
            $this->results["response"]["data"]["error"][] = "error sku '" . $sku . "' - product id <" . $product->getId() . "> " . $e->getMessage();
            $this->log("ERROR: sku '" . $sku . "' - product id <" . $product->getId() . "> " . $e->getMessage());
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__($e->getMessage()), 0, 400);
        }

        // import stock
        try {
            if ($product && $product->getId() && count($productDataToImport['stock']) && !empty($productDataToImport['stock']['qty'])) {
                $_qty            = (int) $productDataToImport['stock']['qty'];
                $minCartQty      = isset($productDataToImport['stock']['min_sale_qty']) ? (int) $productDataToImport['stock']['min_sale_qty'] : '';
                $stockUpdateData = [];
                $stockItem       = $this->stockRegistryInterface->getStockItem($product->getId());
                if (!$stockItem->getId()) {
                    $stockUpdateData['qty'] = 0;
                    $stockUpdateData['is_in_stock'] = 0;
                }

                $oldQty = $stockItem->getQty();
                $oldQty = (int) $oldQty;

                if ($_qty > 0) {
                    if (!$stockItem->getData('is_in_stock')) {
                        $stockUpdateData['is_in_stock'] = 1;
                    }
                }

                if ($_qty <= 0) {
                    if ($stockItem->getData('is_in_stock')) {
                        $stockUpdateData['is_in_stock'] = 0;
                    }
                }

                if ($oldQty != $_qty) {
                    $stockUpdateData['qty']     = $_qty;
                    $stockUpdateData['old_qty'] = $oldQty;
                }

                if ($minCartQty && $stockItem->getData('min_sale_qty') && $stockItem->getData('min_sale_qty') != $minCartQty) {
                    $stockUpdateData['min_sale_qty'] = $minCartQty;
                }

                if (count($stockUpdateData)) {
                    $product->setStockData($stockUpdateData);
                    $product->save();
                    $message = "SAVED QTY: sku: '" . $sku . "' - product id <" . $product->getId() . "> :" . json_encode($stockUpdateData);
                    $this->results["response"]["quantity"]["success"][] = "saved qty sku '" . $sku . "' - product id <" . $product->getId() . "> : " . json_encode($stockUpdateData);
                    $this->log("SAVED QTY: " . $message);
                } else {
                    $this->results["response"]["quantity"]["success"][] = "skip qty sku '" . $sku . "' - product id <" . $product->getId() . "> no data changed";
                    $this->log("SKIP QTY: sku '" . $sku . "' - product id <" . $product->getId() . "> no data changed");
                }
            }
        } catch (\Exception $e) {
            $this->results["response"]["quantity"]["error"][] = "error qty sku '" . $sku . "' - product id <" . $product->getId() . "> " . $e->getMessage();
            $this->log("ERROR QTY: sku '" . $product->getSku() . "' - product id <" . $product->getId() . "> " . $e->getMessage());
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__($e->getMessage()), 0, 400);
        }

        if ($product && $product->getId()) {
            $productLinks  = [];
            $relatedFlag   = false;
            $upSellFlag    = false;
            $crossSellFlag = false;
            // add related products
            if (isset($productDataToImport['attributes']['_related_skus']) && $productDataToImport['attributes']['_related_skus']) {
                $relatedSkusToSet = array_map('trim', explode(',', (string) $productDataToImport['attributes']['_related_skus']));
                $relatedSkusToSet = array_unique($relatedSkusToSet);
                if (count($relatedSkusToSet)) {
                    $relatedProducts    = $product->getRelatedProducts();
                    $relatedProArranged = [];
                    foreach ($relatedProducts as $relatedPro) {
                        $relatedProArranged[$relatedPro->getSku()] = ['position' => $relatedPro->getPosition()];
                    }
                    foreach ($relatedSkusToSet as $relatedSku) {
                        $relatedSku   = trim((string) $relatedSku);
                        $relatedProID = $this->productFactory->create()->getIdBySku($relatedSku);
                        if (!$relatedProID) {
                            $this->results["response"]["data"]["error"][] = "warn related sku '" . $relatedSku . "' not found";
                            $this->log('WARN related sku ' . $relatedSku . ' not found');
                            continue;
                        }
                        if (!array_key_exists($relatedSku, $relatedProArranged)) {
                            $relatedProArranged[$relatedSku] = ['position' => ''];
                            $relatedFlag = true;
                        }
                    }
                    if (count($relatedProArranged)) {
                        foreach ($relatedProArranged as $relatedSku => $relatedPos) {
                            $relatedProductLink = $this->productLink->create()
                                ->setSku($product->getSku())
                                ->setLinkedProductSku($relatedSku)
                                ->setLinkType("related")
                                ->setPosition($relatedPos);
                            $productLinks[] = $relatedProductLink;
                        }
                    }
                }
            }
            // add up-sell products
            if (isset($productDataToImport['attributes']['_upsell_skus']) && $productDataToImport['attributes']['_upsell_skus']) {
                $upSellSkusToSet = array_map('trim', explode(',', (string) $productDataToImport['attributes']['_upsell_skus']));
                $upSellSkusToSet = array_unique($upSellSkusToSet);
                if (count($upSellSkusToSet)) {
                    $upSellProducts    = $product->getUpSellProducts();
                    $upSellProArranged = [];
                    foreach ($upSellProducts as $upSellPro) {
                        $upSellProArranged[$upSellPro->getSku()] = ['position' => $upSellPro->getPosition()];
                    }
                    foreach ($upSellSkusToSet as $upSellSku) {
                        $upSellSku   = trim((string) $upSellSku);
                        $upSellProID = $this->productFactory->create()->getIdBySku($upSellSku);
                        if (!$upSellProID) {
                            $this->results["response"]["data"]["error"][] = "warn up-sell sku '" . $upSellSku . "' not found";
                            $this->log('WARN up-sell sku ' . $upSellSku . ' not found');
                            continue;
                        }
                        if (!array_key_exists($upSellSku, $upSellProArranged)) {
                            $upSellProArranged[$upSellSku] = ['position' => ''];
                            $upSellFlag = true;
                        }
                    }
                    if (count($upSellProArranged)) {
                        foreach ($upSellProArranged as $upSellSku => $upSellPos) {
                            $upSellProductLink = $this->productLink->create()
                                ->setSku($product->getSku())
                                ->setLinkedProductSku($upSellSku)
                                ->setLinkType("upsell")
                                ->setPosition($upSellPos);
                            $productLinks[] = $upSellProductLink;
                        }
                    }
                }
            }
            // add cross-sell products
            if (isset($productDataToImport['attributes']['_crosssell_skus']) && $productDataToImport['attributes']['_crosssell_skus']) {
                $crossSellSkusToSet = array_map('trim', explode(',', (string) $productDataToImport['attributes']['_crosssell_skus']));
                $crossSellSkusToSet = array_unique($crossSellSkusToSet);
                if (count($crossSellSkusToSet)) {
                    $crossSellProducts    = $product->getCrossSellProducts();
                    $crossSellProArranged = [];
                    foreach ($crossSellProducts as $crossSellPro) {
                        $crossSellProArranged[$crossSellPro->getSku()] = ['position' => $crossSellPro->getPosition()];
                    }
                    foreach ($crossSellSkusToSet as $crossSellSku) {
                        $crossSellSku   = trim((string) $crossSellSku);
                        $crossSellProID = $this->productFactory->create()->getIdBySku($crossSellSku);
                        if (!$crossSellProID) {
                            $this->results["response"]["data"]["error"][] = "warn cross-sell sku '" . $crossSellSku . "' not found";
                            $this->log('WARN cross-sell sku ' . $crossSellSku . ' not found');
                            continue;
                        }
                        if (!array_key_exists($crossSellSku, $crossSellProArranged)) {
                            $crossSellProArranged[$crossSellSku] = ['position' => ''];
                            $crossSellFlag = true;
                        }
                    }
                    if (count($crossSellProArranged)) {
                        foreach ($crossSellProArranged as $crossSellSku => $crossSellPos) {
                            $crossSellProductLink = $this->productLink->create()
                                ->setSku($product->getSku())
                                ->setLinkedProductSku($crossSellSku)
                                ->setLinkType("crosssell")
                                ->setPosition($crossSellPos);
                            $productLinks[] = $crossSellProductLink;
                        }
                    }
                }
            }
            if ($relatedFlag || $upSellFlag || $crossSellFlag) {
                if (count($productLinks)) {
                    $product->setProductLinks($productLinks);
                    try {
                        $product->save();
                        if ($relatedFlag) {
                            $this->log("Set related: sku '" . $product->getSku() . "' " . json_encode($relatedSkusToSet));
                        }
                        if ($upSellFlag) {
                            $this->log("Set up-sells: sku '" . $product->getSku() . "' " . json_encode($upSellSkusToSet));
                        }
                        if ($crossSellFlag) {
                            $this->log("Set cross-sells: sku '" . $product->getSku() . "' " . json_encode($crossSellSkusToSet));
                        }
                    } catch (\Exception $e) {
                        $this->results["response"]["data"]["error"][] = "error sku '" . $sku . "' - product id <" . $product->getId() . "> " . $e->getMessage();
                        $this->log("ERROR: sku '" . $sku . "' - product id <" . $product->getId() . "> " . $e->getMessage());
                        $this->cleanResponseMessages();
                        throw new \Magento\Framework\Webapi\Exception(__($e->getMessage()), 0, 400);
                    }
                }
            }
        }

        // import categories
        try {
            if ($product && $product->getId()) {
                if (isset($productDataToImport['category_ids']) && is_array($productDataToImport['category_ids']) && count($productDataToImport['category_ids'])) {
                    $categoryIds = $productDataToImport['category_ids'];
                    $this->setCategories($product, $categoryIds);
                }
            }
        } catch (\Exception $e) {
            $this->results["response"]["category"]["error"][] = "error category sku '" . $sku . "' - product id <" . $product->getId() . "> " . $e->getMessage();
            $message = "ERROR category: sku '" . $sku . "' - product id <" . $product->getId() . "> " . $e->getMessage();
            $this->log($message);
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__($e->getMessage()), 0, 400);
        }

        // import images
        try {
            if ($product && $product->getId() && count($productDataToImport['images'])) {
                $product = $this->productFactory->create()
                                ->setStoreId(0)
                                ->load($product->getId());
                $this->importImages($product, $productDataToImport['images']);
            }
        } catch (\Exception $e) {
            $this->results["response"]["image"]["error"][] = "error sku '" . $sku . "' - product id <" . $product->getId() . "> set image: " . $e->getMessage();
            $this->log("ERROR: sku '" . $sku . "' - product id <" . $product->getId() . "> set image: " . $e->getMessage());
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__($e->getMessage()), 0, 400);
        }

        try {
            if ($product && $product->getId()) {
                $product = $this->productFactory->create()
                                ->setStoreId($storeId)
                                ->load($product->getId());
                // add product to configurable (parent) product if it has parent
                // TO DO check if product has configurable attribute value before add
                if ($productDataToImport['variation']['is_in_relationship']) {
                    if (!$productDataToImport['variation']['is_parent'] && $productDataToImport['variation']['parent_sku']) {
                        $parentSku  = $productDataToImport['variation']['parent_sku'];
                        $parentId   = $this->productFactory->create()->getIdBySku($parentSku);
                        if ($parentId) {
                            $parent = $this->productFactory->create()->load($parentId);
                            $superAttrCodes = $productDataToImport['variation']['super_attribute'];
                            if ($superAttrCodes) {
                                $superAttrCodes = explode(',', (string) $superAttrCodes);
                                sort($superAttrCodes);
                                if ($parent->getTypeId() == 'configurable') {
                                    $childrenProductIds    = $this->configurableProduct->create()->getUsedProductIds($parent);
                                    $needChangConfigurable = false;
                                    $hasParentError        = false;
                                    if (!in_array($product->getId(), $childrenProductIds)) {
                                        $needChangConfigurable = true;
                                    } else {
                                        $extensionConfigurableAttributes = $parent->getExtensionAttributes();
                                        $existingConfigurableOptions     = $extensionConfigurableAttributes->getConfigurableProductOptions();

                                        $existingConfigurableAttrs = [];
                                        foreach ($existingConfigurableOptions as $_configurableOption) {
                                            $attrCode = $_configurableOption->getProductAttribute()->getAttributeCode();
                                            $existingConfigurableAttrs[] = $attrCode;
                                        }
                                        sort($existingConfigurableAttrs);

                                        if ($existingConfigurableAttrs != $superAttrCodes) {
                                            $needChangConfigurable = true;
                                        }
                                    }

                                    if ($needChangConfigurable) {
                                        $associatedProductIds       = [];
                                        $attributeValues            = [];
                                        $configurableAttributesData = [];

                                        $superAttrPositions         = [];
                                        foreach ($superAttrCodes as $superAttrCode) {
                                            $superAttrPositions[$superAttrCode] = count($superAttrPositions) + 1;
                                        }

                                        $extensionConfigurableAttributes = $parent->getExtensionAttributes();
                                        $existingConfigurableOptions     = $extensionConfigurableAttributes->getConfigurableProductOptions();

                                        foreach ($existingConfigurableOptions as $_configurableOption) {
                                            $attrCode = $_configurableOption->getProductAttribute()->getAttributeCode();

                                            if (!isset($superAttrPositions[$attrCode])) {
                                                $this->results["response"]["variation"]["error"][] = "error add child '" . $product->getSku() . "' - product id <" . $product->getId() . "> to parent '" . $parentSku . "': attribute '" . $attrCode . "' is not mapped";
                                                $this->log("ERROR ADD CHILD '" . $product->getSku() . "' - product id <" . $product->getId() . "> to PARENT '" . $parentSku . "': attribute '" . $attrCode . "' is not mapped");
                                                $hasParentError = true;
                                                break;
                                            }

                                            $attributeId = $_configurableOption->getAttributeId();

                                            $options     = $_configurableOption->getOptions();
                                            $values      = [];
                                            foreach ($options as $option) {
                                                $values[] = [
                                                    'label'        => $option['default_label'],
                                                    'attribute_id' => $attributeId,
                                                    'value_index'  => $option['value_index'],
                                                ];
                                            }

                                            $configurableAttributesData[$attrCode] = [
                                                'attribute_id' => $attributeId,
                                                'code'         => $attrCode,
                                                'label'        => $_configurableOption->getProductAttribute()->getFrontendLabel(),
                                                'position'     => $superAttrPositions[$attrCode],
                                                'values'       => $values,
                                            ];
                                        }

                                        foreach ($superAttrCodes as $superAttrCode) {
                                            if (!isset($superAttrPositions[$superAttrCode])) {
                                                $this->results["response"]["variation"]["error"][] = "error add child '" . $product->getSku() . "' - product id <" . $product->getId() . "> to parent '" . $parentSku . "': attribute '" . $superAttrCode . "' is not mapped";
                                                $this->log("ERROR ADD CHILD '" . $product->getSku() . "' - product id <" . $product->getId() . "> to PARENT '" . $parentSku . "': attribute '" . $superAttrCode . "' is not mapped");
                                                $hasParentError = true;
                                                break;
                                            }

                                            if (!$product->getData($superAttrCode)) {
                                                $this->results["response"]["variation"]["error"][] = "error add child '" . $product->getSku() . "' - product id <" . $product->getId() . "> to parent '" . $parentSku . "': attribute '" . $superAttrCode . "' doesn't have value";
                                                $this->log("ERROR ADD CHILD '" . $product->getSku() . "' - product id <" . $product->getId() . "> to PARENT '" . $parentSku . "': attribute '" . $superAttrCode . "' doesn't have value");
                                                $hasParentError = true;
                                                break;
                                            }

                                            $attribute      = $this->productAttributeRepository->get($superAttrCode);
                                            $attributeValue = [
                                                'label'        => $this->getAttributeValue($superAttrCode, $product->getData($superAttrCode)),
                                                'attribute_id' => $attribute->getId(),
                                                'value_index'  => $product->getData($superAttrCode),
                                            ];

                                            if (isset($configurableAttributesData[$superAttrCode])) {
                                                $configurableAttributesData[$superAttrCode]['values'][] = $attributeValue;
                                            } else {
                                                $configurableAttributesData[$superAttrCode] = [
                                                    'attribute_id' => $attribute->getId(),
                                                    'code'         => $superAttrCode,
                                                    'label'        => $attribute->getFrontendLabel(),
                                                    'position'     => $superAttrPositions[$superAttrCode],
                                                    'values'       => [$attributeValue],
                                                ];
                                            }
                                        }

                                        if (!$hasParentError) {
                                            $childrenProductIds[] = $product->getId();
                                            $childrenProductIds   = array_unique($childrenProductIds);

                                            $optionsFactory = $this->productOptionFactory;

                                            $configurableOptions = $optionsFactory->create($configurableAttributesData);

                                            $extensionConfigurableAttributes = $parent->getExtensionAttributes();

                                            $extensionConfigurableAttributes->setConfigurableProductOptions($configurableOptions);
                                            $extensionConfigurableAttributes->setConfigurableProductLinks($childrenProductIds);

                                            $parent->setExtensionAttributes($extensionConfigurableAttributes);

                                            // $this->productRepositoryInterface->save($parent);
                                            $parent->save();
                                            $this->results["response"]["variation"]["success"][] = "added child '" . $product->getSku() . "' - product id <" . $product->getId() . "> to parent '" . $parentSku . "'";
                                            $this->log("ADDED CHILD '" . $product->getSku() . "' - product id <" . $product->getId() . "> to PARENT '" . $parentSku . "'");
                                        }
                                    }
                                } else {
                                    $this->results["response"]["variation"]["error"][] = "warn invalid parent '" . $parentSku . "'";
                                    $this->log("WARN: INVALID PARENT '" . $parentSku . "'");
                                }
                            } else {
                                $this->results["response"]["variation"]["error"][] = "error add child '" . $product->getSku() . "' - product id <" . $product->getId() . "> to parent '" . $parentSku . "': invalid supper attributes";
                                $this->log("ERROR ADD CHILD '" . $product->getSku() . "' - product id <" . $product->getId() . "> to PARENT '" . $parentSku . "': invalid supper attributes");
                            }
                        } else {
                            $this->results["response"]["variation"]["error"][] = "warn not parent '" . $parentSku . "'";
                            $this->log("WARN: NOT PARENT '" . $parentSku . "'");
                        }
                    }
                }

                // add product to grouped (parent) product if it has parent
                if ($product->getTypeId() == 'grouped' && count($productDataToImport['grouped'])) {
                    $childrenProductSkus = $productDataToImport['grouped'];
                    foreach ($childrenProductSkus as $childrenProductSku) {
                        $childrenProductId  = $this->productFactory->create()->getIdBySku($childrenProductSku);
                        if (!$childrenProductId) {
                            continue;
                        }
                        $childrenProduct    = $this->productFactory->create()->load($childrenProductId);
                        $childrenProductIds = $this->groupedProduct->create()->getChildrenIds($product->getId());
                        if (!isset($childrenProductIds[3][$childrenProductId])) {
                            $newLinks      = [];
                            $productLink   = $this->productLink->create();
                            $linkedProduct = $this->productRepositoryInterface->getById($childrenProductId);

                            $productLink->setSku($product->getSku())
                                ->setLinkType('associated')
                                ->setLinkedProductSku($linkedProduct->getSku())
                                ->setLinkedProductType($linkedProduct->getTypeId())
                                ->setPosition(1)
                                ->getExtensionAttributes()
                                ->setQty(1);

                            $newLinks[] = $productLink;
                            $childrenProduct->setProductLinks($newLinks);

                            // $this->productRepositoryInterface->save($product);
                            $childrenProduct->save();
                            $this->results["response"]["variation"]["success"][] = "added child '" . $childrenProduct->getSku() . "' - product id <" . $product->getId() . "> to grouped parent '" . $product->getSku() . "'";
                            $this->log("ADDED CHILD '" . $childrenProduct->getSku() . "' - product id <" . $product->getId() . "> to GROUPED PARENT '" . $product->getSku() . "'");
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->results["response"]["variation"]["error"][] = "error add child '" . $product->getSku() . "' - product id <" . $product->getId() . "> to parent '" . $parentSku . "': " . $e->getMessage();
            $this->log("ERROR ADD CHILD '" . $product->getSku() . "' - product id <" . $product->getId() . "> to PARENT '" . $parentSku . "': " . $e->getMessage());
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__($e->getMessage()), 0, 400);
        }
    }

    public function setCategories($product, $categoryIds)
    {
        $sku            = $product->getSku();
        $categoryIds    = array_unique($categoryIds);
        sort($categoryIds);
        $oldCategoryIds = $product->getCategoryIds();
        sort($oldCategoryIds);

        if ($oldCategoryIds != $categoryIds) {
            $product->setCategoryIds($categoryIds);
            $product->save();
            // $this->categoryLinkManagementInterface->assignProductToCategories($sku, $categoryIds);
            $this->results["response"]["category"]["success"][] = "set category sku '" . $sku . "' - product id <" . $product->getId() . "> [" . implode(",", $categoryIds) . "] old [" . implode(",", $oldCategoryIds) . "]";
            $message = "SET category: sku '" . $sku . "' - product id <" . $product->getId() . "> " . json_encode($categoryIds) . " old " . json_encode($oldCategoryIds);
            $this->log($message);
        }
    }

    public function getDataToImport($attributeInfo, $product, $variationInfo, $groupedInfo, $stockInfo, $imageInfo, $storeId)
    {
        $result                 = [];
        $result['attributes']   = []; // for product fields
        $result['category_ids'] = []; // for category ids
        $result['variation']    = $variationInfo; // for product variation
        $result['grouped']      = $groupedInfo;   // for grouped product
        $result['stock']        = $stockInfo;     // for stock item fields
        $result['images']       = $imageInfo;     // for product images

        $categoryIdsToSet = [];
        $attrsInSet       = $this->productAttributeManagement->getAttributes($product->getData("attribute_set_id"));
        $attrCodesInSet   = ['type_id'];
        foreach ($attrsInSet as $_attrInSet) {
            $attrCodesInSet[] = $_attrInSet->getData("attribute_code");
        }

        // loop for attributeInfo
        foreach ($attributeInfo as $attrCode => $attrValue) {
            if ($product->getResource()->getAttribute($attrCode)) {
                // check if attribute is in product attribute set
                if (!in_array($attrCode, $attrCodesInSet)) {
                    continue;
                }
            }

            // skip attribute is in stock attributes
            if (in_array($attrCode, ['qty', 'is_in_stock', 'min_sale_qty'])) {
                continue;
            }

            if ($attrCode == 'status') {
                if (strtolower((string) $attrValue) == 'true' || $attrValue == "Disabled") {
                    $attrValue = 2;
                } else {
                    $attrValue = 1;
                }
            }

            if ($attrCode == 'visibility') {
                if ($attrValue == 'Catalog, Search') {
                    $attrValue = 4;
                } elseif ($attrValue == 'Search') {
                    $attrValue = 3;
                } elseif ($attrValue == 'Catalog') {
                    $attrValue = 2;
                } else {
                    $attrValue = 1;
                }
            }

            if ($attrCode == 'tax_class') {
                if ($attrValue) {
                    if ($attrValue == 'None') {
                        $attrValue = 0;
                    } else {
                        $taxClassCollection = $this->classModelFactory
                            ->create()
                            ->getCollection()
                            ->addFieldToFilter('class_type', \Magento\Tax\Model\ClassModel::TAX_CLASS_TYPE_PRODUCT)
                            ->addFieldToFilter('class_name', $attrValue);
                        if ($taxClassCollection->getSize()) {
                            $attrValue = $taxClassCollection->getFirstItem()->getClassId();
                        } else {
                            $attrValue = null;
                        }
                    }
                } else {
                    $attrValue = null;
                }
            }

            if ($attrCode == 'category_name') {
                if ($attrValue) {
                    $foundCatIds = $this->categoryHelper->processCategoryTree($attrValue, $storeId, $allowCreateCat = 0);
                    if (!count($foundCatIds)) {
                        $this->results["response"]["category"]["error"][] = "warn category '" . $attrValue . "' not found";
                        $this->log("WARN: category '" . $attrValue . "' not found");
                    }
                    $categoryIdsToSet = array_merge($categoryIdsToSet, $foundCatIds);
                }
                // deal with categories later
                continue;
            }

            if (is_null($attrValue) || $attrValue == "") {
                continue;
            }

            if ($attrCode == 'weight') {
                $weightValue = floatval($attrValue);
                if ($weightValue <= 0) {
                    continue;
                }
            }

            if ($attrCode == "attribute_set") {
                if ($attrValue) {
                    $entityTypeId = $this->entityFactory
                        ->create()
                        ->setType('catalog_product')
                        ->getTypeId();
                    $attributeSetId = $this->entityAttributeSetFactory
                        ->create()
                        ->getCollection()
                        ->setEntityTypeFilter($entityTypeId)
                        ->addFieldToFilter('attribute_set_name', $attrValue)
                        ->getFirstItem()
                        ->getAttributeSetId();
                    if ($attributeSetId) {
                        $attrValue = $attributeSetId;
                    } else {
                        $attrValue = null;
                    }
                } else {
                    $attrValue = null;
                }
            }

            if ($attrCode == "special_from_date") {
                $specialPris = $product->getData("special_price");
                if ($specialPris && $attrValue == null) {
                    continue;
                }
            }

            // set price 0 for parent product doesn't not have price
            if ($result['variation']['is_parent']) {
                if ((is_null($product->getPrice())) && (!isset($result['attributes']['price']) || $result['attributes']['price'] == null)) {
                    $result['attributes']['price'] = 0;
                }
            }

            $result['attributes'][$attrCode] = $attrValue;
        }
        // end loop for attributeInfo

        $result['category_ids'] = $categoryIdsToSet;

        return $result;
    }

    /*
        import images from io
        return number of images added
     */
    public function importImages($product, $imageList)
    {
        if (!$product || !$product->getId()) {
            return 0;
        }

        if (count($imageList)) {
            $addedImagesCount = $this->imageHelper->populateProductImage($product, $imageList, $this);

            return $addedImagesCount;
        }

        return 0;
    }

    public function getAttribute($attrCode)
    {
        return $this->productAttributeHelper->getAttribute($attrCode);
    }

    public function getAttributeValue($attrCode, $attrOptionLabel)
    {
        $attrValue = $this->productAttributeHelper->getAttributeOptionValue($attrCode, $attrOptionLabel);

        if (!$attrValue) {
            $this->results["response"]["data"]["error"][] = "error attribute '" . $attrCode . "' can't find option: '" . $attrOptionLabel . "'";
            $this->log("ERROR: attribute '" . $attrCode . "' can't find option: '" . $attrOptionLabel . "'");
            return null;
        }

        return $attrValue;
    }

    public function updateAtProductAfterSaveAttribute($productId)
    {
        $attributeCode = 'updated_at';
        $attribute     = $this->entityAttributeFactory->create()->loadByCode(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
        if ($attribute->getData('backend_type') != "static") {
            $backend_type = $attribute->getData('backend_type');
        } else {
            $backend_type = "";
        }

        $resource   = $this->resourceConnection;
        $connection = $resource->getConnection('core_write');
        $tableName  = $resource->getTableName(['catalog_product_entity', $backend_type]);
        $columnName = 'entity_id';

        if ($resource->getConnection()->tableColumnExists($tableName, $columnName) !== true) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');

        // update data into table
        $sql = "Update " . $tableName . " SET updated_at ='" . $now . "' WHERE entity_id=" . $productId;
        $connection->query($sql);
    }

    public function cleanResponseMessages()
    {
        if (count($this->results["response"])) {
            foreach ($this->results["response"] as $key => $value) {
                if (isset($value["success"]) && !count($value["success"])) {
                    unset($this->results["response"][$key]["success"]);
                }
                if (isset($value["error"]) && !count($value["error"])) {
                    unset($this->results["response"][$key]["error"]);
                }
                if (isset($this->results["response"][$key]) && !count($this->results["response"][$key])) {
                    unset($this->results["response"][$key]);
                }
                if (isset($this->results["response"][$key]["success"]) && count($this->results["response"][$key]["success"])) {
                    $this->results["response"][$key]["success"] = array_unique($this->results["response"][$key]["success"]);
                }
                if (isset($this->results["response"][$key]["error"]) && count($this->results["response"][$key]["error"])) {
                    $this->results["response"][$key]["error"] = array_unique($this->results["response"][$key]["error"]);
                }
            }
        }
    }

    public function log($message)
    {
        $logDir = $this->filesystem->getDirectoryWrite(DirectoryList::LOG);
        $writer = new \Zend_Log_Writer_Stream($logDir->getAbsolutePath('') . $this->logFile);
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info(print_r($message, true));

        if ($this->showLog) {
            print_r($message);
            echo "\n";
        }
    }
}
