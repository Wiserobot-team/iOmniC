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

class ProductManagement implements \WiseRobot\Io\Api\ProductManagementInterface
{
    /**
     * @var Zend_Log
     */
    public $logger;
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
    public array $results = [];
    /**
     * @var array
     */
    public $attributeSetCache = [];
    /**
     * Define Constants
     */
    public const VISIBILITY_CATALOG_SEARCH = 4;
    public const VISIBILITY_NOT_VISIBLE = 1;
    public const STATUS_ENABLED = 1;
    public const TAX_CLASS_NONE = 0;
    public const PRODUCT_TYPE_SIMPLE = 'simple';
    public const PRODUCT_TYPE_CONFIGURABLE = 'configurable';
    public const PRODUCT_TYPE_GROUPED = 'grouped';
    /**
     * @var array
     */
    public $newProductDefaults = [];
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
        $this->initializeResults();
        $this->initializeLogger();
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
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function import(
        int $store,
        array $attributeInfo,
        array $variationInfo,
        array $groupedInfo = [],
        array $stockInfo = [],
        array $imageInfo = []
    ): array {
        try {
            $this->validateProductInfo($store, $attributeInfo, $variationInfo);
            $sku = $attributeInfo["sku"];
            $product = $this->getProductToImport(
                $store,
                $sku,
                $attributeInfo,
                $variationInfo,
                $groupedInfo
            );
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
                $productData = array_merge($productData, $this->newProductDefaults);
            }
            $this->importData($product, $productData, $store);
            $this->cleanResponseMessages();
            return $this->results;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Validate Product Info
     *
     * @param int $store
     * @param mixed $attributeInfo
     * @param mixed $variationInfo
     * @return void
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function validateProductInfo(int $store, mixed $attributeInfo, mixed $variationInfo): void
    {
        if (empty($store)) {
            $this->addMessageAndLog("Field: 'store' is a required field", "error");
        }
        if (empty($attributeInfo) || !is_array($attributeInfo)) {
            $this->addMessageAndLog("Field: 'attribute_info' is a required field", "error");
        }
        if (is_array($attributeInfo) && (!isset($attributeInfo["sku"]) || empty($attributeInfo["sku"]))) {
            $this->addMessageAndLog("Field: 'attribute_info' - 'sku' data is a required", "error");
        }
        if (empty($variationInfo) || !is_array($variationInfo)) {
            $this->addMessageAndLog("Field: 'variation_info' is a required field", "error");
        } else {
            $requiredKeys = ["is_in_relationship", "is_parent", "parent_sku", "super_attribute"];
            $missingKeys = array_diff_key(array_flip($requiredKeys), $variationInfo);
            if (!empty($missingKeys)) {
                $missingKeyList = implode(', ', array_keys($missingKeys));
                $this->addMessageAndLog("Field: 'variation_info' is missing required fields: {$missingKeyList}", "error");
            }
        }
    }

    /**
     * Retrieves the Product Model
     *
     * @param int $store
     * @param string $sku
     * @param array $attributeInfo
     * @param array $variationInfo
     * @param array $groupedInfo
     * @return \Magento\Catalog\Model\Product
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function getProductToImport(
        int $store,
        string $sku,
        array $attributeInfo,
        array $variationInfo,
        array $groupedInfo
    ): \Magento\Catalog\Model\Product {
        $storeInfo = $this->getStoreInfo($store);
        $productId = $this->productFactory->create()->getIdBySku($sku);
        if ($productId) {
            $this->isNewProduct = false;
            $product = $this->productFactory->create()->setStoreId($store)->load($productId);
            if (!$product->getId()) {
                $this->addMessageAndLog("ERROR: sku '" . $sku . "' can't load product", "error");
            }
            return $product;
        }

        $this->isNewProduct = true;
        $this->newProductDefaults = ['default' => []];
        $product = $this->productFactory->create();

        $ioProductImages = $this->productImageFactory->create()
            ->getCollection()
            ->addFieldToFilter('sku', $sku);
        foreach ($ioProductImages as $ioProductImage) {
            $ioProductImage->delete();
        }

        $attributeSetId = $this->productResource->getEntityType()->getDefaultAttributeSetId();
        if (!empty($attributeInfo['attribute_set'])) {
            $attributeSetId = $this->_getAttributeSetId($attributeInfo['attribute_set'], $sku);
        }
        $product->setData('attribute_set_id', $attributeSetId);
        $this->newProductDefaults['default']['attribute_set'] = $attributeSetId;

        if ($store != 0) {
            $websiteId = $storeInfo->getData('website_id');
            $product->setWebsiteIds([$websiteId]);
        }
        $product->setData('sku', $sku);
        $this->newProductDefaults['default']['sku'] = $sku;
        $product->setData('status', self::STATUS_ENABLED);
        $this->newProductDefaults['default']['status'] = self::STATUS_ENABLED;
        $product->setData('tax_class_id', self::TAX_CLASS_NONE);
        $this->newProductDefaults['default']['tax_class'] = self::TAX_CLASS_NONE;

        $productType = self::PRODUCT_TYPE_SIMPLE;
        if ($variationInfo['is_parent']) {
            $productType = self::PRODUCT_TYPE_CONFIGURABLE;
        } elseif (count($groupedInfo)) {
            $productType = self::PRODUCT_TYPE_GROUPED;
        }
        $product->setData('type_id', $productType);
        $this->newProductDefaults['default']['type_id'] = $productType;

        $visibility = self::VISIBILITY_CATALOG_SEARCH;
        if ($variationInfo['is_in_relationship'] && ($productType == self::PRODUCT_TYPE_SIMPLE)) {
            $visibility = self::VISIBILITY_NOT_VISIBLE;
        }
        $product->setData('visibility', $visibility);
        $this->newProductDefaults['default']['visibility'] = $visibility;

        $product->setStockData(["qty" => 0, "is_in_stock" => 1]);

        return $product;
    }

    /**
     * Get Store Info
     *
     * @param int $store
     * @return \Magento\Store\Model\Store
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function getStoreInfo(
        int $store
    ): \Magento\Store\Model\Store {
        try {
            return $this->storeManager->getStore($store);
        } catch (\Exception $e) {
            $this->addMessageAndLog("Requested 'store' {$store} doesn't exist", "error");
        }
    }

    /**
     * Retrieves the Attribute Set ID
     *
     * @param string $attrSetName
     * @param string $sku
     * @return int
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function _getAttributeSetId(string $attrSetName, string $sku): int
    {
        if (isset($this->attributeSetCache[$attrSetName])) {
            return $this->attributeSetCache[$attrSetName];
        }
        $entityTypeId = $this->productResource->getEntityType()->getEntityTypeId();
        $attributeSet = $this->entityAttributeSetFactory->create()
            ->getCollection()
            ->setEntityTypeFilter($entityTypeId)
            ->addFieldToFilter('attribute_set_name', $attrSetName)
            ->getFirstItem();

        $attributeSetId = (int) $attributeSet->getAttributeSetId();
        if (!$attributeSetId) {
            $this->addMessageAndLog("ERROR: sku '" . $sku . "' attribute_set: '" . $attrSetName . "' doesn't exist", "error");
        }
        $this->attributeSetCache[$attrSetName] = $attributeSetId;

        return $attributeSetId;
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
        \Magento\Catalog\Model\Product $product,
        array $productData,
        int $storeId
    ): bool {
        $sku = $product->getSku();
        try {
            $importedAttributes = [];
            if (isset($productData['attributes']) && is_array($productData['attributes'])) {
                foreach ($productData['attributes'] as $attrCode => $attrValue) {
                    if (in_array($attrCode, $this->ignoreAttributes)) {
                        continue;
                    }
                    if (in_array($attrCode, ['_related_skus', '_upsell_skus', '_crosssell_skus'])) {
                        continue;
                    }
                    if ($attrCode == "url_key" && empty($attrValue)) {
                        continue;
                    }
                    switch ($attrCode) {
                        case 'attribute_set':
                            $attrCode = 'attribute_set_id';
                            break;
                        case 'tax_class':
                            $attrCode = 'tax_class_id';
                            break;
                    }
                    if (!in_array($attrCode, ['attribute_set_id', 'visibility', 'tax_class_id', 'status'])) {
                        // some case doesn't treat like select
                        $attribute = $this->getAttribute($attrCode);
                        if ($attribute && $attribute->getData("frontend_input") == "select" &&
                            !$attrValue = $this->getAttributeValue($attrCode, $attrValue)) {
                            // TODO: find way to set empty value for dropdown
                            continue;
                        }
                    }
                    if ($product->getData($attrCode) != $attrValue) {
                        $product->setData($attrCode, $attrValue);
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
                    $this->urlPersistInterface->replace(
                        $this->productUrlRewriteGenerator->generate($product)
                    );
                } catch (\Exception $error) {
                    $this->log('Error while generating url: ' . $error->getMessage());
                }
                $message = "SAVED: sku '" . $sku . "' - product id <" . $product->getId() . "> saved successful";
                $this->results["response"]["data"]["success"][] = $message;
                $this->log($message);
            } else {
                if (count($importedAttributes)) {
                    $message = "REQUEST: sku '" . $sku . "' - product id <" .
                        $product->getId() . "> : " . json_encode($importedAttributes);
                    $this->log($message);
                    // update the product updated_at
                    $this->updateProductUpdatedAt((int) $product->getId());
                    $message = "SAVED: sku '" . $sku . "' - product id <" . $product->getId() . "> saved successful";
                    $this->results["response"]["data"]["success"][] = $message;
                    $this->log($message);
                } else {
                    $message = "SKIP: sku '" . $sku . "' - product id <" . $product->getId() . "> no data was changed";
                    $this->results["response"]["data"]["success"][] = $message;
                    $this->log($message);
                }
            }
        } catch (\Exception $e) {
            $message = "ERROR: sku '" . $sku . "' - product id <" . $product->getId() . "> " . $e->getMessage();
            $this->results["response"]["data"]["error"][] = $message;
            $this->log($message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($e->getMessage()), 0, 400);
        }

        // import stock
        try {
            if ($product && $product->getId() && isset($productData['stock']['qty'])) {
                $productId = $product->getId();
                $stockData = $productData['stock'];
                $stockUpdateData = [];
                $stockItem = $this->stockRegistryInterface->getStockItem($productId);
                if (!$stockItem->getId()) {
                    $stockUpdateData = [
                        'qty' => 0,
                        'is_in_stock' => 0
                    ];
                }
                $oldQty = (int) $stockItem->getQty();
                $isInStock = (bool) $stockItem->getData('is_in_stock');
                $currentMinSaleQty = (int) $stockItem->getData('min_sale_qty');
                $newQty = (int) $stockData['qty'];
                $minCartQty = isset($stockData['min_sale_qty']) ? (int) $stockData['min_sale_qty'] : null;
                if ($newQty > 0 && !$isInStock) {
                    $stockUpdateData['is_in_stock'] = 1;
                }
                if ($newQty <= 0 && $isInStock) {
                    $stockUpdateData['is_in_stock'] = 0;
                }
                if ($oldQty !== $newQty) {
                    $stockUpdateData['qty'] = $newQty;
                    $stockUpdateData['old_qty'] = $oldQty;
                }
                if ($minCartQty && $currentMinSaleQty !== $minCartQty) {
                    $stockUpdateData['min_sale_qty'] = $minCartQty;
                }
                if (!empty($stockUpdateData)) {
                    $product->setStockData($stockUpdateData);
                    $product->save();
                    $message = "SAVED QTY: sku: '" . $sku . "' - product id <" .
                        $productId . "> : " . json_encode($stockUpdateData);
                    $this->results["response"]["quantity"]["success"][] = $message;
                    $this->log($message);
                } else {
                    $message = "SKIP QTY: sku '" . $sku . "' - product id <" . $productId . "> no data was changed";
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

        // import related, up-sell, and cross-sell products
        if ($product && $product->getId()) {
            $productLinks = $product->getProductLinks();
            $productId = $product->getId();
            $linkTypes = [
                'related' => '_related_skus',
                'upsell' => '_upsell_skus',
                'crosssell' => '_crosssell_skus'
            ];
            $updatedSkus = [];
            $existingLinksBySkuAndType = [];
            foreach ($productLinks as $link) {
                $existingLinksBySkuAndType[$link->getLinkType()][$link->getLinkedProductSku()] = true;
            }

            foreach ($linkTypes as $linkType => $attributeCode) {
                if (isset($productData['attributes'][$attributeCode]) && $productData['attributes'][$attributeCode]) {
                    $skusToSet = array_unique(
                        array_map('trim', explode(',', (string) $productData['attributes'][$attributeCode]))
                    );
                    $newSkus = [];
                    foreach ($skusToSet as $newSku) {
                        if (!isset($existingLinksBySkuAndType[$linkType][$newSku])) {
                            $newSkus[] = $newSku;
                        }
                    }
                    if (!empty($newSkus)) {
                        $productCollection = $this->productFactory->create()
                            ->getCollection()
                            ->addAttributeToSelect('sku')
                            ->addAttributeToFilter('sku', ['in' => $newSkus]);

                        $productIds = [];
                        foreach ($productCollection as $_product) {
                            $productIds[$_product->getSku()] = $_product->getId();
                        }

                        $tempSkus = [];
                        foreach ($newSkus as $newSku) {
                            if (isset($productIds[$newSku])) {
                                $productLink = $this->productLink->create()
                                    ->setSku($sku)
                                    ->setLinkedProductSku($newSku)
                                    ->setLinkType($linkType)
                                    ->setPosition('');
                                $productLinks[] = $productLink;
                                $tempSkus[] = $newSku;
                            } else {
                                $this->log("WARN: " . $linkType . " sku '" . $newSku . "' not found");
                            }
                        }
                        if (!empty($tempSkus)) {
                            $updatedSkus[$linkType] = $tempSkus;
                        }
                    }
                }
            }
            if (!empty($updatedSkus)) {
                try {
                    $product->setProductLinks($productLinks);
                    $product->save();
                    foreach ($updatedSkus as $linkType => $skus) {
                        $message = "SET " . $linkType . ": sku '" . $sku . "' - product id <" .
                            $productId . "> : " . json_encode($skus);
                        $this->log($message);
                    }
                } catch (\Exception $e) {
                    $message = "ERROR: sku '" . $sku . "' - product id <" . $productId . "> " . $e->getMessage();
                    $this->results["response"]["data"]["error"][] = $message;
                    $this->log($message);
                    $this->cleanResponseMessages();
                    throw new \Magento\Framework\Webapi\Exception(__($e->getMessage()), 0, 400);
                }
            }
        }

        // import categories
        try {
            if ($product && $product->getId() && isset($productData['category_ids'])) {
                $categoryIds = $productData['category_ids'];
                if (is_array($categoryIds) && !empty($categoryIds)) {
                    $this->setCategories($product, $categoryIds);
                }
            }
        } catch (\Exception $e) {
            $message = "ERROR category: sku '" . $sku . "' - product id <" .
                $product->getId() . "> " . $e->getMessage();
            $this->results["response"]["category"]["error"][] = $message;
            $this->log($message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($e->getMessage()), 0, 400);
        }

        // import images
        try {
            if ($product && $product->getId() && !empty($productData['images'])) {
                $product = $this->productFactory->create()
                    ->setStoreId(0)
                    ->load($product->getId());
                $this->importImages($product, $productData['images']);
            }
        } catch (\Exception $e) {
            $productId = $product->getId();
            $message = "ERROR: sku '" . $sku . "' - product id <" .
                $productId . "> set image: " . $e->getMessage();
            $this->results["response"]["image"]["error"][] = $message;
            $this->log($message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($e->getMessage()), 0, 400);
        }

        // import variations
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
        \Magento\Catalog\Model\Product $product,
        array $categoryIds
    ): void {
        $categoryIds = array_unique($categoryIds);
        $categoryIds = array_map('intval', $categoryIds);
        sort($categoryIds);

        $oldCategoryIds = $product->getCategoryIds();
        $oldCategoryIds = array_map('intval', $oldCategoryIds);
        sort($oldCategoryIds);

        if ($oldCategoryIds !== $categoryIds) {
            // $this->categoryLinkManagementInterface->assignProductToCategories($sku, $categoryIds);
            $product->setCategoryIds($categoryIds);
            $product->save();
            $message = "SET category sku '" . $product->getSku() . "' - product id <" .
                $product->getId() . "> : [" . implode(",", $categoryIds) . "] old [" .
                implode(",", $oldCategoryIds) . "]";
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
        array $attributeInfo,
        \Magento\Catalog\Model\Product $product,
        array $variationInfo,
        array $groupedInfo,
        array $stockInfo,
        array $imageInfo,
        int $storeId
    ): array {
        $result = [
            'attributes' => [],
            'category_ids' => [],
            'variation' => $variationInfo,
            'grouped' => $groupedInfo,
            'stock' => $stockInfo,
            'images' => $imageInfo,
        ];
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
            if ($attrValue === null || $attrValue == "") {
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
            if ($attrCode == "category_ids") {
                if ($attrValue) {
                    $attrValue = array_map('trim', explode(',', (string) $attrValue));
                    $categoryIdsToSet = array_merge($categoryIdsToSet, $attrValue);
                }
                // deal with categories later
                continue;
            }
            if ($attrCode == 'category_name') {
                if ($attrValue) {
                    $foundCatIds = $this->categoryHelper->processCategoryTree(
                        $attrValue,
                        $storeId,
                        $allowCreateCat = false
                    );
                    if (empty($foundCatIds)) {
                        $message = "WARN: category '" . $attrValue . "' not found";
                        $this->results["response"]["category"]["error"][] = $message;
                        $this->log($message);
                    } else {
                        $categoryIdsToSet = array_merge($categoryIdsToSet, $foundCatIds);
                    }
                }
                // deal with categories later
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
                if ($product->getData("special_price") && empty($attrValue)) {
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

        $result['category_ids'] = array_unique(array_map('intval', $categoryIdsToSet));

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
        \Magento\Catalog\Model\Product $product,
        array $imageList
    ): mixed {
        if (!$product || !$product->getId()) {
            return 0;
        }
        if (!empty($imageList)) {
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
    public function getAttribute(string $attrCode): mixed
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
    public function getAttributeValue(string $attrCode, string $attrOptionLabel): mixed
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
     * Update product updated_at after saving an attribute.
     *
     * @param int $productId
     * @return bool
     */
    public function updateProductUpdatedAt(int $productId): bool
    {
        try {
            $connection = $this->resourceConnection->getConnection('core_write');
            $tableName = $this->resourceConnection->getTableName('catalog_product_entity');
            $now = gmdate('Y-m-d H:i:s');
            $bind = [
                'updated_at' => $now
            ];
            $where = ['entity_id = ?' => $productId];
            $connection->update($tableName, $bind, $where);
            return true;
        } catch (\Exception $e) {
            $this->log("ERROR: update product updated_at for product id <" . $productId . "> " . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize results structure
     *
     * @return void
     */
    public function initializeResults(): void
    {
        $sections = ['data', 'quantity', 'category', 'image', 'variation'];
        $this->results['response'] = [];
        foreach ($sections as $section) {
            $this->results['response'][$section] = [
                'success' => [],
                'error' => [],
            ];
        }
    }

    /**
     * Add message and log
     *
     * @param string $message
     * @param string $type
     * @param int $logLevel
     * @return void
     */
    public function addMessageAndLog(string $message, string $type, int $logLevel = 1): void
    {
        $this->results["response"]["data"][$type][] = $message;
        $this->cleanResponseMessages();
        if ($logLevel) {
            $this->log($message);
        }
        if ($type === "error") {
            throw new WebapiException(__($message), 0, 400, $this->results["response"]);
        }
    }

    /**
     * Clean response message
     *
     * @return void
     */
    public function cleanResponseMessages(): void
    {
        if (!empty($this->results["response"])) {
            foreach ($this->results["response"] as $key => &$value) {
                foreach (['success', 'error'] as $type) {
                    if (!empty($value[$type])) {
                        $value[$type] = array_unique(array_filter($value[$type]));
                    } else {
                        unset($value[$type]);
                    }
                }
                if (empty($value)) {
                    unset($this->results["response"][$key]);
                }
            }
        }
    }

    /**
     * Initialize the logger
     *
     * @return void
     */
    public function initializeLogger(): void
    {
        $logDir = $this->filesystem->getDirectoryWrite(DirectoryList::LOG);
        $writer = new \Zend_Log_Writer_Stream($logDir->getAbsolutePath($this->logFile));
        $this->logger = new \Zend_Log();
        $this->logger->addWriter($writer);
    }

    /**
     * Log message
     *
     * @param string $message
     * @return void
     */
    public function log(string $message): void
    {
        if ($this->logger) {
            $this->logger->info($message);
        }
    }
}
