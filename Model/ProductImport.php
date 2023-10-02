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

namespace WiseRobot\Io\Model;

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
use Magento\Framework\Webapi\Exception as WebapiException;
use WiseRobot\Io\Helper\ProductAttribute as ProductAttributeHelper;
use WiseRobot\Io\Helper\Category as CategoryHelper;
use WiseRobot\Io\Helper\Image as ImageHelper;
use WiseRobot\Io\Model\ProductImageFactory;

class ProductImport implements \WiseRobot\Io\Api\ProductImportInterface
{
    /**
     * @var string
     */
    public $logFile = "wr_io_product_import.log";
    /**
     * @var bool
     */
    public $isNewProduct = false;
    /**
     * @var array
     */
    public $results = [];
    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfig;
    /**
     * @var Filesystem
     */
    public $filesystem;
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;
    /**
     * @var ConfigurableProduct
     */
    public $configurableProduct;
    /**
     * @var GroupedProduct
     */
    public $groupedProduct;
    /**
     * @var ClassModelFactory
     */
    public $classModelFactory;
    /**
     * @var ProductLinkInterfaceFactory
     */
    public $productLink;
    /**
     * @var AttributeRepository
     */
    public $productAttributeRepository;
    /**
     * @var CategoryLinkManagementInterface
     */
    public $categoryLinkManagementInterface;
    /**
     * @var ProductUrlRewriteGenerator
     */
    public $productUrlRewriteGenerator;
    /**
     * @var UrlPersistInterface
     */
    public $urlPersistInterface;
    /**
     * @var ConfigurableOptionFactory
     */
    public $productOptionFactory;
    /**
     * @var ProductRepositoryInterface
     */
    public $productRepositoryInterface;
    /**
     * @var ProductAttributeManagement
     */
    public $productAttributeManagement;
    /**
     * @var ProductFactory
     */
    public $productFactory;
    /**
     * @var StockRegistryInterface
     */
    public $stockRegistryInterface;
    /**
     * @var ProductCollectionFactory
     */
    public $productCollectionFactory;
    /**
     * @var EntityFactory
     */
    public $entityFactory;
    /**
     * @var EntityAttributeFactory
     */
    public $entityAttributeFactory;
    /**
     * @var EntityAttributeSetFactory
     */
    public $entityAttributeSetFactory;
    /**
     * @var ResourceConnection
     */
    public $resourceConnection;
    /**
     * @var TimezoneInterface
     */
    public $timezoneInterface;
    /**
     * @var ProductResource
     */
    public $productResource;
    /**
     * @var CategoryFactory
     */
    public $categoryFactory;
    /**
     * @var AttributeRepositoryInterface
     */
    public $attributeRepositoryInterface;
    /**
     * @var ProductAttributeHelper
     */
    public $productAttributeHelper;
    /**
     * @var CategoryHelper
     */
    public $categoryHelper;
    /**
     * @var ImageHelper
     */
    public $imageHelper;
    /**
     * @var ProductImageFactory
     */
    public $productImageFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     * @param ConfigurableProduct $configurableProduct
     * @param GroupedProduct $groupedProduct
     * @param ClassModelFactory $classModelFactory
     * @param ProductLinkInterfaceFactory $productLink
     * @param AttributeRepository $productAttributeRepository
     * @param CategoryLinkManagementInterface $categoryLinkManagementInterface
     * @param ProductUrlRewriteGenerator $productUrlRewriteGenerator
     * @param UrlPersistInterface $urlPersistInterface
     * @param ConfigurableOptionFactory $productOptionFactory
     * @param ProductRepositoryInterface $productRepositoryInterface
     * @param ProductAttributeManagement $productAttributeManagement
     * @param ProductFactory $productFactory
     * @param StockRegistryInterface $stockRegistryInterface
     * @param ProductCollectionFactory $productCollectionFactory
     * @param EntityFactory $entityFactory
     * @param EntityAttributeFactory $entityAttributeFactory
     * @param EntityAttributeSetFactory $entityAttributeSetFactory
     * @param ResourceConnection $resourceConnection
     * @param TimezoneInterface $timezoneInterface
     * @param ProductResource $productResource
     * @param CategoryFactory $categoryFactory
     * @param AttributeRepositoryInterface $attributeRepositoryInterface
     * @param ProductAttributeHelper $productAttributeHelper
     * @param CategoryHelper $categoryHelper
     * @param ImageHelper $imageHelper
     * @param ProductImageFactory $productImageFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        ConfigurableProduct $configurableProduct,
        GroupedProduct $groupedProduct,
        ClassModelFactory $classModelFactory,
        ProductLinkInterfaceFactory $productLink,
        AttributeRepository $productAttributeRepository,
        CategoryLinkManagementInterface $categoryLinkManagementInterface,
        ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        UrlPersistInterface $urlPersistInterface,
        ConfigurableOptionFactory $productOptionFactory,
        ProductRepositoryInterface $productRepositoryInterface,
        ProductAttributeManagement $productAttributeManagement,
        ProductFactory $productFactory,
        StockRegistryInterface $stockRegistryInterface,
        ProductCollectionFactory $productCollectionFactory,
        EntityFactory $entityFactory,
        EntityAttributeFactory $entityAttributeFactory,
        EntityAttributeSetFactory $entityAttributeSetFactory,
        ResourceConnection $resourceConnection,
        TimezoneInterface $timezoneInterface,
        ProductResource $productResource,
        CategoryFactory $categoryFactory,
        AttributeRepositoryInterface $attributeRepositoryInterface,
        ProductAttributeHelper $productAttributeHelper,
        CategoryHelper $categoryHelper,
        ImageHelper $imageHelper,
        ProductImageFactory $productImageFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        $this->configurableProduct = $configurableProduct;
        $this->groupedProduct = $groupedProduct;
        $this->classModelFactory = $classModelFactory;
        $this->productLink = $productLink;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->categoryLinkManagementInterface = $categoryLinkManagementInterface;
        $this->productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->urlPersistInterface = $urlPersistInterface;
        $this->productOptionFactory = $productOptionFactory;
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->productAttributeManagement = $productAttributeManagement;
        $this->productFactory = $productFactory;
        $this->stockRegistryInterface = $stockRegistryInterface;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->entityFactory = $entityFactory;
        $this->entityAttributeFactory = $entityAttributeFactory;
        $this->entityAttributeSetFactory = $entityAttributeSetFactory;
        $this->resourceConnection = $resourceConnection;
        $this->timezoneInterface = $timezoneInterface;
        $this->productResource = $productResource;
        $this->categoryFactory = $categoryFactory;
        $this->attributeRepositoryInterface = $attributeRepositoryInterface;
        $this->productAttributeHelper = $productAttributeHelper;
        $this->productAttributeHelper->productAttribute = $this;
        $this->categoryHelper = $categoryHelper;
        $this->categoryHelper->logModel = $this;
        $this->imageHelper = $imageHelper;
        $this->productImageFactory = $productImageFactory;
    }

    /**
     * @var string[]
     */
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

    /**
     * Import Products
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
        $store,
        $attributeInfo,
        $variationInfo,
        $groupedInfo = [],
        $stockInfo = [],
        $imageInfo = []
    ) {
        // response messages
        $this->results["response"]["data"]["success"] = [];
        $this->results["response"]["data"]["error"] = [];
        $this->results["response"]["quantity"]["success"] = [];
        $this->results["response"]["quantity"]["error"] = [];
        $this->results["response"]["category"]["success"] = [];
        $this->results["response"]["category"]["error"] = [];
        $this->results["response"]["image"]["success"] = [];
        $this->results["response"]["image"]["error"] = [];
        $this->results["response"]["variation"]["success"] = [];
        $this->results["response"]["variation"]["error"] = [];

        $errorMess = "data request error";

        // store info
        if (!$store) {
            $message = "Field: 'store' is a required field";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log("ERROR: " . $message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }
        try {
            $storeInfo = $this->storeManager->getStore($store);
        } catch (\Exception $e) {
            $message = "Requested 'store' " . $store . " doesn't exist > " . $e->getMessage();
            $this->results["response"]["data"]["error"][] = $message;
            $this->log("ERROR: " . $message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }

        // attribute info
        if (!$attributeInfo) {
            $message = "Field: 'attribute_info' is a required field";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log("ERROR: " . $message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }
        if (!isset($attributeInfo["sku"])) {
            $message = "Field: 'attribute_info' - 'sku' data is a required";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log("ERROR: " . $message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }
        if (!isset($attributeInfo["gtin"])) {
            $message = "Field: 'attribute_info' - 'gtin' data is a required";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log("ERROR: " . $message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }

        // variation info
        if (!$variationInfo) {
            $message = "Field: 'variation_info' is a required field";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log("ERROR: " . $message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }
        if (!isset($variationInfo["is_in_relationship"]) || !isset($variationInfo["is_parent"]) ||
            !isset($variationInfo["parent_sku"]) || !isset($variationInfo["super_attribute"])) {
            $message = "Field: 'attribute_info' - data error";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log("ERROR: " . $message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }

        $sku = $attributeInfo["sku"];
        $productId = $this->productFactory->create()->getIdBySku($sku);
        $defaultAttrSetId = $this->productResource->getEntityType()->getDefaultAttributeSetId();
        $attributeSetId = "";
        if (!empty($attributeInfo['attribute_set'])) {
            $attrSetName = $attributeInfo['attribute_set'];
            $entityTypeId = $this->entityFactory->create()
                ->setType('catalog_product')
                ->getTypeId();
            $attributeSetId = $this->entityAttributeSetFactory->create()
                ->getCollection()
                ->setEntityTypeFilter($entityTypeId)
                ->addFieldToFilter('attribute_set_name', $attrSetName)
                ->getFirstItem()
                ->getAttributeSetId();
            if (!$attributeSetId) {
                $message = "ERROR: sku '" . $sku . "' attribute_set: '" . $attrSetName . "' doesn't exist";
                $this->results["response"]["data"]["error"][] = $message;
                $this->log($message);
                $this->cleanResponseMessages();
                throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
            }
        }

        $createParent = false;
        if (!$productId) {
            $this->isNewProduct = true;
            $product = $this->productFactory->create();

            $ioProductImages = $this->productImageFactory->create()
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
            $product->setData('gtin', $attributeInfo["gtin"]);
            $defaultDataToImport['default']['gtin'] = $attributeInfo["gtin"];
            /* code added by ktpl */

            if ($attributeSetId) {
                $product->setData('attribute_set_id', $attributeSetId);
                $defaultDataToImport['default']['attribute_set'] = $attributeSetId;
            } else {
                $product->setData('attribute_set_id', $defaultAttrSetId);
                $defaultDataToImport['default']['attribute_set'] = $defaultAttrSetId;
            }

            if ($variationInfo['is_parent']) {
                $createParent = true;
                $product->setData('type_id', 'configurable');
                $defaultDataToImport['default']['type_id'] = 'configurable';
            }

            if (count($groupedInfo)) {
                $createParent = true;
                $product->setData('type_id', 'grouped');
                $defaultDataToImport['default']['type_id'] = 'grouped';
            }

            $defaultVisibility = 4; // Catalog, Search
            $product->setData('visibility', $defaultVisibility);
            $defaultDataToImport['default']['visibility'] = $defaultVisibility;

            if ($variationInfo['is_in_relationship'] && !$variationInfo['is_parent']) {
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

            $product->setStockData(
                [
                    "qty" => 0,
                    "is_in_stock" => 1
                ]
            );
        } else {
            $product = $this->productFactory->create()
                ->setStoreId($store)
                ->load($productId);
            if (!$product->getId()) {
                $message = "ERROR: sku '" . $sku . "' can't load product";
                $this->results["response"]["data"]["error"][] = $message;
                $this->log($message);
                $this->cleanResponseMessages();
                throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
            }
        }

        $productData = $this->getDataToImport(
            $attributeInfo,
            $product,
            $variationInfo,
            $groupedInfo,
            $stockInfo,
            $imageInfo,
            $store
        );
        if ($this->isNewProduct) {
            $productData = array_merge($productData, $defaultDataToImport);
        }

        try {
            $this->importData($product, $productData, $store);
            $this->cleanResponseMessages();
            return $this->results;
        } catch (\Exception $e) {
            $errorMess = "product import error";
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }
    }

    /**
     * Import Io Product
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param array $productData
     * @param int $storeId
     * @return bool
     */
    public function importData(
        $product,
        $productData,
        $storeId
    ) {
        $sku = $product->getSku();
        try {
            $importedAttributes = [];
            if (count($productData['attributes'])) {
                foreach ($productData['attributes'] as $attrCode => $attrValue) {
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
                    if (in_array($attrCode, ['attribute_set', 'visibility', 'tax_class', 'status'])) {
                        continue;
                    }

                    // some case doesn't treat like select
                    $attribute = $this->getAttribute($attrCode);
                    if ($attribute && $attribute->getData("frontend_input") == "select") {
                        $attrValue = $this->getAttributeValue($attrCode, $attrValue);
                        if (!$attrValue) {
                            // TODO: find way to set empty value for dropdown
                            continue;
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
                $message = "REQUEST: sku '" . $sku . "' " .
                    json_encode(array_merge($productData['default'], $importedAttributes));
                $this->log($message);
                // $this->productRepositoryInterface->save($product);
                $product->save();
                try {
                    $this->urlPersistInterface
                        ->replace($this->productUrlRewriteGenerator->generate($product));
                } catch (\Exception $error) {
                    $this->log('Error while generate url: ' . $error->getMessage());
                }
                $message = "SAVED: sku '" . $sku . "' - product id <" . $product->getId() . "> saved successful";
                $this->results["response"]["data"]["success"][] = $message;
                $this->log($message);
            } else {
                if (count($importedAttributes)) {
                    $message = "REQUEST: sku '" . $sku . "' - product id <" .
                        $product->getId() . ">" . json_encode($importedAttributes);
                    $this->log($message);
                    // change product update at after use saveAttribute
                    $this->updateAtProductAfterSaveAttribute((int) $product->getId());
                    $message = "SAVED: sku '" . $sku . "' - product id <" . $product->getId() . "> saved successful";
                    $this->results["response"]["data"]["success"][] = $message;
                    $this->log($message);
                } else {
                    $message = "SKIP: sku '" . $sku . "' - product id <" . $product->getId() . "> no data changed";
                    $this->results["response"]["data"]["success"][] = $message;
                    $this->log($message);
                }
            }
        } catch (\Exception $e) {
            $message = "ERROR: sku :: '" . $sku . "' - product id <" . $product->getId() . "> " . $e->getMessage();
            $this->results["response"]["data"]["error"][] = $message;
            $this->log($message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($e->getMessage()), 0, 400);
        }

        // import stock
        try {
            if ($product && $product->getId() &&
                count($productData['stock']) && !empty($productData['stock']['qty'])) {
                $productId = $product->getId();
                $stockData = $productData['stock'];
                $_qty = (int) $stockData['qty'];
                $minCartQty = isset($stockData['min_sale_qty']) ? (int) $stockData['min_sale_qty'] : '';
                $stockUpdateData = [];
                $stockItem = $this->stockRegistryInterface->getStockItem($productId);
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
                    $stockUpdateData['qty'] = $_qty;
                    $stockUpdateData['old_qty'] = $oldQty;
                }
                if ($minCartQty && $stockItem->getData('min_sale_qty') &&
                    $stockItem->getData('min_sale_qty') != $minCartQty) {
                    $stockUpdateData['min_sale_qty'] = $minCartQty;
                }
                if (count($stockUpdateData)) {
                    $product->setStockData($stockUpdateData);
                    $product->save();
                    $message = "SAVED QTY: sku: '" . $sku . "' - product id <" .
                        $productId . "> :" . json_encode($stockUpdateData);
                    $this->results["response"]["quantity"]["success"][] = $message;
                    $this->log($message);
                } else {
                    $message = "SKIP QTY: sku '" . $sku . "' - product id <" . $productId . "> no data changed";
                    $this->results["response"]["quantity"]["success"][] = $message;
                    $this->log($message);
                }
            }
        } catch (\Exception $e) {
            $message = "ERROR QTY: sku '" . $sku . "' - product id <" .
                $productId . "> " . $e->getMessage();
            $this->results["response"]["quantity"]["error"][] = $message;
            $this->log($message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($e->getMessage()), 0, 400);
        }

        if ($product && $product->getId()) {
            $productId = $product->getId();
            $productLinks = [];
            $relatedFlag = false;
            $upSellFlag = false;
            $crossSellFlag = false;
            // add related products
            if (isset($productData['attributes']['_related_skus']) && $productData['attributes']['_related_skus']) {
                $relatedSkus = explode(',', (string) $productData['attributes']['_related_skus']);
                $relatedSkusToSet = array_map('trim', $relatedSkus);
                $relatedSkusToSet = array_unique($relatedSkusToSet);
                if (count($relatedSkusToSet)) {
                    $relatedProducts = $product->getRelatedProducts();
                    $relatedProArranged = [];
                    foreach ($relatedProducts as $relatedPro) {
                        $relatedProArranged[$relatedPro->getSku()] = ['position' => $relatedPro->getPosition()];
                    }
                    foreach ($relatedSkusToSet as $relatedSku) {
                        $relatedSku = trim((string) $relatedSku);
                        $relatedProID = $this->productFactory->create()->getIdBySku($relatedSku);
                        if (!$relatedProID) {
                            $message = 'WARN related sku ' . $relatedSku . ' not found';
                            $this->results["response"]["data"]["error"][] = $message;
                            $this->log($message);
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
                                ->setSku($sku)
                                ->setLinkedProductSku($relatedSku)
                                ->setLinkType("related")
                                ->setPosition($relatedPos);
                            $productLinks[] = $relatedProductLink;
                        }
                    }
                }
            }
            // add up-sell products
            if (isset($productData['attributes']['_upsell_skus']) && $productData['attributes']['_upsell_skus']) {
                $upSellSkus = explode(',', (string) $productData['attributes']['_upsell_skus']);
                $upSellSkusToSet = array_map('trim', $upSellSkus);
                $upSellSkusToSet = array_unique($upSellSkusToSet);
                if (count($upSellSkusToSet)) {
                    $upSellProducts = $product->getUpSellProducts();
                    $upSellProArranged = [];
                    foreach ($upSellProducts as $upSellPro) {
                        $upSellProArranged[$upSellPro->getSku()] = ['position' => $upSellPro->getPosition()];
                    }
                    foreach ($upSellSkusToSet as $upSellSku) {
                        $upSellSku = trim((string) $upSellSku);
                        $upSellProID = $this->productFactory->create()->getIdBySku($upSellSku);
                        if (!$upSellProID) {
                            $message = 'WARN up-sell sku ' . $upSellSku . ' not found';
                            $this->results["response"]["data"]["error"][] = $message;
                            $this->log($message);
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
                                ->setSku($sku)
                                ->setLinkedProductSku($upSellSku)
                                ->setLinkType("upsell")
                                ->setPosition($upSellPos);
                            $productLinks[] = $upSellProductLink;
                        }
                    }
                }
            }
            // add cross-sell products
            if (isset($productData['attributes']['_crosssell_skus']) && $productData['attributes']['_crosssell_skus']) {
                $crossSellSkus = explode(',', (string) $productData['attributes']['_crosssell_skus']);
                $crossSellSkusToSet = array_map('trim', $crossSellSkus);
                $crossSellSkusToSet = array_unique($crossSellSkusToSet);
                if (count($crossSellSkusToSet)) {
                    $crossSellProducts = $product->getCrossSellProducts();
                    $crossSellProArranged = [];
                    foreach ($crossSellProducts as $crossSellPro) {
                        $crossSellProArranged[$crossSellPro->getSku()] = ['position' => $crossSellPro->getPosition()];
                    }
                    foreach ($crossSellSkusToSet as $crossSellSku) {
                        $crossSellSku = trim((string) $crossSellSku);
                        $crossSellProID = $this->productFactory->create()->getIdBySku($crossSellSku);
                        if (!$crossSellProID) {
                            $message = 'WARN cross-sell sku ' . $crossSellSku . ' not found';
                            $this->results["response"]["data"]["error"][] = $message;
                            $this->log($message);
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
                                ->setSku($sku)
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
                            $this->log("Set related: sku '" . $sku . "' " . json_encode($relatedSkusToSet));
                        }
                        if ($upSellFlag) {
                            $this->log("Set up-sells: sku '" . $sku . "' " . json_encode($upSellSkusToSet));
                        }
                        if ($crossSellFlag) {
                            $this->log("Set cross-sells: sku '" . $sku . "' " . json_encode($crossSellSkusToSet));
                        }
                    } catch (\Exception $e) {
                        $message = "ERROR: sku '" . $sku . "' - product id <" . $productId . "> " . $e->getMessage();
                        $this->results["response"]["data"]["error"][] = $message;
                        $this->log($message);
                        $this->cleanResponseMessages();
                        throw new WebapiException(__($e->getMessage()), 0, 400);
                    }
                }
            }
        }

        // import categories
        try {
            if ($product && $product->getId()) {
                if (isset($productData['category_ids']) && is_array($productData['category_ids'])
                    && count($productData['category_ids'])) {
                    $categoryIds = $productData['category_ids'];
                    $this->setCategories($product, $categoryIds);
                }
            }
        } catch (\Exception $e) {
            $productId = $product->getId();
            $message = "ERROR category: sku '" . $sku . "' - product id <" . $productId . "> " . $e->getMessage();
            $this->results["response"]["category"]["error"][] = $message;
            $this->log($message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($e->getMessage()), 0, 400);
        }

        // import images
        try {
            if ($product && $product->getId() && count($productData['images'])) {
                $product = $this->productFactory->create()
                    ->setStoreId(0)
                    ->load($product->getId());
                $this->importImages($product, $productData['images']);
            }
        } catch (\Exception $e) {
            $productId = $product->getId();
            $message = "ERROR: sku '" . $sku . "' - product id <" . $productId . "> set image: " . $e->getMessage();
            $this->results["response"]["image"]["error"][] = $message;
            $this->log($message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($e->getMessage()), 0, 400);
        }

        try {
            if (!$product || !$product->getId()) {
                $message = "ERROR: sku '" . $sku . "' can't load product";
                $this->results["response"]["variation"]["error"][] = $message;
                $this->log($message);
                return false;
            }
            $product = $this->productFactory->create()
                ->setStoreId($storeId)
                ->load($product->getId());
            $productId = $product->getId();
            if ($product->getTypeId() != 'grouped') {
                // add product to configurable (parent) product if it has parent
                // TO DO check if product has configurable attribute value before add
                if (!$productData['variation']['is_in_relationship'] ||
                    $productData['variation']['is_parent'] ||
                    !$productData['variation']['parent_sku']) {
                    return false;
                }
                $parentSku = $productData['variation']['parent_sku'];
                $parentId = $this->productFactory->create()
                    ->getIdBySku($parentSku);
                if (!$parentId) {
                    $message = "WARN: NOT PARENT '" . $parentSku . "'";
                    $this->results["response"]["variation"]["error"][] = $message;
                    $this->log($message);
                    return false;
                }

                $parent = $this->productFactory->create()->load($parentId);
                $superAttrCodes = $productData['variation']['super_attribute'];
                if (!$superAttrCodes) {
                    $message = "ERROR ADD CHILD '" . $sku . "' - product id <" .
                        $product->getId() . "> to PARENT '" . $parentSku . "': invalid supper attributes";
                    $this->results["response"]["variation"]["error"][] = $message;
                    $this->log($message);
                    return false;
                }

                $superAttrCodes = explode(',', (string) $superAttrCodes);
                sort($superAttrCodes);
                if ($parent->getTypeId() != 'configurable') {
                    $message = "WARN: INVALID PARENT '" . $parentSku . "'";
                    $this->results["response"]["variation"]["error"][] = $message;
                    $this->log($message);
                    return false;
                }

                $childrenProductIds = $this->configurableProduct->create()
                    ->getUsedProductIds($parent);
                $needChangConfigurable = false;
                $hasParentError = false;
                if (!in_array($productId, $childrenProductIds)) {
                    $needChangConfigurable = true;
                } else {
                    $configurableAttributes = $parent->getExtensionAttributes();
                    $configurableOptions = $configurableAttributes->getConfigurableProductOptions();

                    $existingConfigurableAttrs = [];
                    foreach ($configurableOptions as $_configurableOption) {
                        $attrCode = $_configurableOption->getProductAttribute()->getAttributeCode();
                        $existingConfigurableAttrs[] = $attrCode;
                    }
                    sort($existingConfigurableAttrs);

                    if ($existingConfigurableAttrs != $superAttrCodes) {
                        $needChangConfigurable = true;
                    }
                }

                if ($needChangConfigurable) {
                    $configurableAttrData = [];
                    $superAttrPositions = [];
                    foreach ($superAttrCodes as $superAttrCode) {
                        $superAttrPositions[$superAttrCode] = count($superAttrPositions) + 1;
                    }
                    $configurableAttributes = $parent->getExtensionAttributes();
                    $configurableOptions = $configurableAttributes->getConfigurableProductOptions();

                    foreach ($configurableOptions as $_configurableOption) {
                        $attrCode = $_configurableOption->getProductAttribute()->getAttributeCode();

                        if (!isset($superAttrPositions[$attrCode])) {
                            $message = "ERROR ADD CHILD '" . $sku . "' - product id <" .
                                $productId . "> to PARENT '" . $parentSku . "': attribute '" .
                                $attrCode . "' is not mapped";
                            $this->results["response"]["variation"]["error"][] = $message;
                            $this->log($message);
                            $hasParentError = true;
                            break;
                        }

                        $attributeId = $_configurableOption->getAttributeId();
                        $options = $_configurableOption->getOptions();
                        $values = [];
                        foreach ($options as $option) {
                            $values[] = [
                                'label' => $option['default_label'],
                                'attribute_id' => $attributeId,
                                'value_index' => $option['value_index'],
                            ];
                        }

                        $configurableAttrData[$attrCode] = [
                            'attribute_id' => $attributeId,
                            'code' => $attrCode,
                            'label' => $_configurableOption->getProductAttribute()
                                ->getFrontendLabel(),
                            'position' => $superAttrPositions[$attrCode],
                            'values' => $values,
                        ];
                    }

                    foreach ($superAttrCodes as $superAttrCode) {
                        if (!isset($superAttrPositions[$superAttrCode])) {
                            $message = "ERROR ADD CHILD '" . $sku . "' - product id <" .
                                $productId . "> to PARENT '" . $parentSku . "': attribute '" .
                                $superAttrCode . "' is not mapped";
                            $this->results["response"]["variation"]["error"][] = $message;
                            $this->log($message);
                            $hasParentError = true;
                            break;
                        }

                        if (!$product->getData($superAttrCode)) {
                            $message = "ERROR ADD CHILD '" . $sku . "' - product id <" .
                                $productId . "> to PARENT '" . $parentSku . "': attribute '" .
                                $superAttrCode . "' doesn't have value";
                            $this->results["response"]["variation"]["error"][] = $message;
                            $this->log($message);
                            $hasParentError = true;
                            break;
                        }

                        $attribute = $this->productAttributeRepository->get($superAttrCode);
                        $optionLabel = $product->getResource()
                            ->getAttribute($superAttrCode)
                            ->setStoreId($storeId)
                            ->getFrontend()
                            ->getValue($product);
                        $attributeValue = [
                            'label' => $this->getAttributeValue(
                                $superAttrCode,
                                $optionLabel
                            ),
                            'attribute_id' => $attribute->getId(),
                            'value_index' => $product->getData($superAttrCode),
                        ];

                        if (isset($configurableAttrData[$superAttrCode])) {
                            $configurableAttrData[$superAttrCode]['values'][] = $attributeValue;
                        } else {
                            $configurableAttrData[$superAttrCode] = [
                                'attribute_id' => $attribute->getId(),
                                'code' => $superAttrCode,
                                'label' => $attribute->getFrontendLabel(),
                                'position' => $superAttrPositions[$superAttrCode],
                                'values' => [$attributeValue],
                            ];
                        }
                    }

                    if (!$hasParentError) {
                        $childrenProductIds[] = $product->getId();
                        $childrenProductIds = array_unique($childrenProductIds);

                        $optionsFactory = $this->productOptionFactory;
                        $configurableOptions = $optionsFactory->create($configurableAttrData);
                        $configurableAttributes = $parent->getExtensionAttributes();

                        $configurableAttributes->setConfigurableProductOptions(
                            $configurableOptions
                        );
                        $configurableAttributes->setConfigurableProductLinks($childrenProductIds);

                        $parent->setExtensionAttributes($configurableAttributes);

                        // $this->productRepositoryInterface->save($parent);
                        $parent->save();
                        $message = "ADDED CHILD '" . $sku . "' - product id <" .
                            $product->getId() . "> to PARENT '" . $parentSku . "'";
                        $this->results["response"]["variation"]["success"][] = $message;
                        $this->log($message);
                    }
                }
            } else {
                // add product to grouped (parent) product if it has parent
                if (count($productData['grouped'])) {
                    $childrenProductSkus = $productData['grouped'];
                    foreach ($childrenProductSkus as $childrenProductSku) {
                        $childrenProductId = $this->productFactory->create()
                            ->getIdBySku($childrenProductSku);
                        if (!$childrenProductId) {
                            continue;
                        }
                        $childrenProduct = $this->productFactory->create()
                            ->load($childrenProductId);
                        $childrenProductIds = $this->groupedProduct->create()
                            ->getChildrenIds($product->getId());
                        if (!isset($childrenProductIds[3][$childrenProductId])) {
                            $newLinks = [];
                            $productLink = $this->productLink->create();
                            $linkedProduct = $this->productRepositoryInterface->getById($childrenProductId);

                            $productLink->setSku($sku)
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
                            $chillSku = $childrenProduct->getSku();
                            $message = "ADDED CHILD '" . $chillSku . "' - product id <" .
                                $product->getId() . "> to grouped parent '" . $sku . "'";
                            $this->results["response"]["variation"]["success"][] = $message;
                            $this->log($message);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $message = "ERROR ADD CHILD '" . $sku . "' - product id <" .
                $product->getId() . "> to PARENT '" . $parentSku . "': " . $e->getMessage();
            $this->results["response"]["variation"]["error"][] = $message;
            $this->log($message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($e->getMessage()), 0, 400);
        }

        return true;
    }

    /**
     * Set Categories for Product
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param array $categoryIds
     * @return void
     */
    public function setCategories(
        $product,
        $categoryIds
    ) {
        $sku = $product->getSku();
        $productId = $product->getId();
        $categoryIds = array_unique($categoryIds);
        sort($categoryIds);
        $oldCategoryIds = $product->getCategoryIds();
        sort($oldCategoryIds);
        if ($oldCategoryIds != $categoryIds) {
            $product->setCategoryIds($categoryIds);
            $product->save();
            // $this->categoryLinkManagementInterface->assignProductToCategories($sku, $categoryIds);
            $message = "SET category sku '" . $sku . "' - product id <" .
                $productId . ">" . " [" . implode(",", $categoryIds) . "] old [" . implode(",", $oldCategoryIds) . "]";
            $this->results["response"]["category"]["success"][] = $message;
            $this->log($message);
        }
    }

    /**
     * Get data to import
     *
     * @param array $attributeInfo
     * @param \Magento\Catalog\Model\Product $product
     * @param string[] $variationInfo
     * @param string[] $groupedInfo
     * @param string[] $stockInfo
     * @param string[] $imageInfo
     * @param int $storeId
     * @return array
     */
    public function getDataToImport(
        $attributeInfo,
        $product,
        $variationInfo,
        $groupedInfo,
        $stockInfo,
        $imageInfo,
        $storeId
    ) {
        $result = [];
        $result['attributes'] = []; // for product fields
        $result['category_ids'] = []; // for category ids
        $result['variation'] = $variationInfo; // for product variation
        $result['grouped'] = $groupedInfo; // for grouped product
        $result['stock'] = $stockInfo; // for stock item fields
        $result['images'] = $imageInfo; // for product images

        $categoryIdsToSet = [];
        $attrsInSet = $this->productAttributeManagement->getAttributes(
            $product->getData("attribute_set_id")
        );
        $attrCodesInSet = ['type_id'];
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
                        $taxClassCollection = $this->classModelFactory->create()
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
                    $allowCreateCat = false;
                    $foundCatIds = $this->categoryHelper->processCategoryTree(
                        $attrValue,
                        $storeId,
                        $allowCreateCat
                    );
                    if (!count($foundCatIds)) {
                        $message = "WARN: category '" . $attrValue . "' not found";
                        $this->results["response"]["category"]["error"][] = $message;
                        $this->log($message);
                    }
                    $categoryIdsToSet[] = $foundCatIds;
                }
                // deal with categories later
                continue;
            }
            if ($attrValue === null || $attrValue == "") {
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
                    $entityTypeId = $this->entityFactory->create()
                        ->setType('catalog_product')
                        ->getTypeId();
                    $attributeSetId = $this->entityAttributeSetFactory->create()
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
            // set price 0 for parent product doesn't have price
            if ($result['variation']['is_parent']) {
                if (($product->getPrice() === null) &&
                    (!isset($result['attributes']['price']) || $result['attributes']['price'] == null)) {
                    $result['attributes']['price'] = 0;
                }
            }

            $result['attributes'][$attrCode] = $attrValue;
        }
        // end loop for attributeInfo

        $result['category_ids'] = array_merge([], ...$categoryIdsToSet);

        return $result;
    }

    /**
     * Import Images from Io
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param array $imageList
     * @return int
     */
    public function importImages(
        $product,
        $imageList
    ) {
        if (!$product || !$product->getId()) {
            return 0;
        }
        if (count($imageList)) {
            $addedImagesCount = $this->imageHelper->populateProductImage($product, $imageList, $this);
            return $addedImagesCount;
        }

        return 0;
    }

    /**
     * Get attribute data
     *
     * @param string $attrCode
     * @return mixed
     */
    public function getAttribute($attrCode)
    {
        return $this->productAttributeHelper->getAttribute($attrCode);
    }

    /**
     * Get attribute value
     *
     * @param string $attrCode
     * @param string $attrOptionLabel
     * @return mixed
     */
    public function getAttributeValue($attrCode, $attrOptionLabel)
    {
        $attrValue = $this->productAttributeHelper->getAttributeOptionValue($attrCode, $attrOptionLabel);
        if (!$attrValue) {
            $message = "ERROR: attribute '" . $attrCode . "' can't find option: '" . $attrOptionLabel . "'";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log($message);
            return null;
        }

        return $attrValue;
    }

    /**
     * Update product after save attribute
     *
     * @param int $productId
     * @return bool
     */
    public function updateAtProductAfterSaveAttribute($productId)
    {
        $attributeCode = 'updated_at';
        $attribute = $this->entityAttributeFactory->create()
            ->loadByCode('catalog_product', $attributeCode);
        if ($attribute->getData('backend_type') != "static") {
            $backendType = $attribute->getData('backend_type');
        } else {
            $backendType = "";
        }

        $resource = $this->resourceConnection;
        $connection = $resource->getConnection('core_write');
        $tableName = $resource->getTableName(['catalog_product_entity', $backendType]);
        $columnName = 'entity_id';

        if ($resource->getConnection()->tableColumnExists($tableName, $columnName) !== true) {
            return false;
        }

        $now = gmdate('Y-m-d H:i:s');

        // update data into table
        $sql = "Update " . $tableName . " SET updated_at ='" . $now . "' WHERE entity_id=" . $productId;
        $connection->query($sql);

        return true;
    }

    /**
     * Clean response message
     *
     * @return void
     */
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
                if (isset($this->results["response"][$key]) &&
                    !count($this->results["response"][$key])) {
                    unset($this->results["response"][$key]);
                }
                if (isset($this->results["response"][$key]["success"]) &&
                    count($this->results["response"][$key]["success"])) {
                    $successData = array_unique($this->results["response"][$key]["success"]);
                    $this->results["response"][$key]["success"] = $successData;
                }
                if (isset($this->results["response"][$key]["error"]) &&
                    count($this->results["response"][$key]["error"])) {
                    $errorData = array_unique($this->results["response"][$key]["error"]);
                    $this->results["response"][$key]["error"] = $errorData;
                }
            }
        }
    }

    /**
     * Log message
     *
     * @param string $message
     * @return void
     */
    public function log($message)
    {
        $logDir = $this->filesystem->getDirectoryWrite(DirectoryList::LOG);
        $writer = new \Zend_Log_Writer_Stream($logDir->getAbsolutePath('') . $this->logFile);
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info($message);
    }
}
