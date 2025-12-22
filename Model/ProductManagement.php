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
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResourceModel;
use Magento\GroupedProduct\Model\Product\Type\GroupedFactory as GroupedProduct;
use Magento\Tax\Model\ClassModelFactory;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Model\CategoryLinkRepository;
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
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Tax\Model\ResourceModel\TaxClass\CollectionFactory as TaxClassCollectionFactory;
use Magento\Framework\Webapi\Exception as WebapiException;
use WiseRobot\Io\Helper\ProductAttribute as ProductAttributeHelper;
use WiseRobot\Io\Helper\Category as CategoryHelper;
use WiseRobot\Io\Helper\Image as ImageHelper;

class ProductManagement implements \WiseRobot\Io\Api\ProductManagementInterface
{
    /**
     * @var Zend_Log
     */
    public $logger;
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
     * @var ConfigurableResourceModel
     */
    public $configurableResourceModel;
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
     * @var CategoryLinkRepository
     */
    public $categoryLinkRepository;
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
     * @var AttributeSetCollectionFactory
     */
    public $attributeSetCollectionFactory;
    /**
     * @var TaxClassCollectionFactory
     */
    public $taxClassCollectionFactory;
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
     * @var string
     */
    public string $logFile = 'wr_io_product_import.log';
    /**
     * @var bool
     */
    public bool $isNewProduct = false;
    /**
     * @var array
     */
    public array $results = [];
    /**
     * @var array
     */
    public array $attributeSetCache = [];
    /**
     * @var array
     */
    public array $taxClassCache = [];
    /**
     * @var array
     */
    public array $attributeCodesInSetCache = [];
    /**
     * @var array
     */
    public array $newProductDefaults = [];
    /**
     * @var string
     */
    public string $ioImagesField = 'io_images';
    /**
     * @var bool
     */
    public bool $allowCreateCategory = false;
    /**
     * Define Constants
     */
    public const PRODUCT_TYPE_SIMPLE = 'simple';
    public const PRODUCT_TYPE_CONFIGURABLE = 'configurable';
    public const PRODUCT_TYPE_GROUPED = 'grouped';
    public const STATUS_ENABLED = 1;
    public const STATUS_DISABLED = 2;
    public const TAX_CLASS_NONE = 0;
    public const VISIBILITY_NOT_VISIBLE = 1;
    public const VISIBILITY_IN_CATALOG = 2;
    public const VISIBILITY_IN_SEARCH = 3;
    public const VISIBILITY_CATALOG_SEARCH = 4;

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
     * @param CategoryLinkRepository $categoryLinkRepository
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
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        ConfigurableProduct $configurableProduct,
        ConfigurableResourceModel $configurableResourceModel,
        GroupedProduct $groupedProduct,
        ClassModelFactory $classModelFactory,
        ProductLinkInterfaceFactory $productLink,
        AttributeRepository $productAttributeRepository,
        CategoryLinkManagementInterface $categoryLinkManagementInterface,
        CategoryLinkRepository $categoryLinkRepository,
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
        AttributeSetCollectionFactory $attributeSetCollectionFactory,
        TaxClassCollectionFactory $taxClassCollectionFactory,
        ProductAttributeHelper $productAttributeHelper,
        CategoryHelper $categoryHelper,
        ImageHelper $imageHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        $this->configurableProduct = $configurableProduct;
        $this->configurableResourceModel = $configurableResourceModel;
        $this->groupedProduct = $groupedProduct;
        $this->classModelFactory = $classModelFactory;
        $this->productLink = $productLink;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->categoryLinkManagementInterface = $categoryLinkManagementInterface;
        $this->categoryLinkRepository = $categoryLinkRepository;
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
        $this->attributeSetCollectionFactory = $attributeSetCollectionFactory;
        $this->taxClassCollectionFactory = $taxClassCollectionFactory;
        $this->productAttributeHelper = $productAttributeHelper;
        $this->productAttributeHelper->productManagement = $this;
        $this->categoryHelper = $categoryHelper;
        $this->categoryHelper->productManagement = $this;
        $this->imageHelper = $imageHelper;
        $this->imageHelper->productManagement = $this;
        $this->preloadAttributeSets();
        $this->preloadTaxClasses();
        $this->initializeResults();
        $this->initializeLogger();
    }

    /**
     * List of attributes to ignore during import
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
     * Imports product data
     *
     * @param int $store
     * @param string[] $attributeInfo
     * @param string[] $variationInfo
     * @param string[] $groupedInfo
     * @param string[] $stockInfo
     * @param string[] $imageInfo
     * @param string[] $customInfo
     * @return array
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function import(
        int $store,
        array $attributeInfo,
        array $variationInfo,
        array $groupedInfo = [],
        array $stockInfo = [],
        array $imageInfo = [],
        array $customInfo = []
    ): array {
        try {
            $this->validateProductInfo($store, $attributeInfo, $variationInfo);
            $sku = $attributeInfo['sku'];
            $this->ioImagesField = !empty($customInfo['io_images_field'])
                ? (string) $customInfo['io_images_field']
                : $this->ioImagesField;
            $this->allowCreateCategory = !empty($customInfo['allow_create_category'])
                ? (bool) $customInfo['allow_create_category']
                : $this->allowCreateCategory;
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
                $customInfo,
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
     * Validates required product information fields
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
     * Preloads all Attribute Set names into the cache
     *
     * @return void
     */
    public function preloadAttributeSets(): void
    {
        if (!empty($this->attributeSetCache)) {
            return;
        }
        $entityTypeId = $this->productResource->getEntityType()->getEntityTypeId();
        $collection = $this->attributeSetCollectionFactory->create()
            ->setEntityTypeFilter($entityTypeId)
            ->addFieldToSelect(['attribute_set_id', 'attribute_set_name']);
        foreach ($collection as $item) {
            $this->attributeSetCache[$item->getAttributeSetName()] = (int) $item->getAttributeSetId();
        }
    }

    /**
     * Preloads all Tax Class names into the cache
     *
     * @return void
     */
    public function preloadTaxClasses(): void
    {
        if (!empty($this->taxClassCache)) {
            return;
        }
        $collection = $this->taxClassCollectionFactory->create()
            ->addFieldToSelect(['class_id', 'class_name']);
        foreach ($collection as $item) {
            $this->taxClassCache[$item->getClassName()] = (int) $item->getClassId();
        }
    }

    /**
     * Retrieves the product model for import
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

        $attributeSetId = (int) $this->productResource->getEntityType()->getDefaultAttributeSetId();
        if (!empty($attributeInfo['attribute_set'])) {
            $attributeSetName = $attributeInfo['attribute_set'];
            $foundAttributeSetId = (int) $this->getAttributeSetIdByName($attributeSetName);
            if (!$foundAttributeSetId) {
                $message = "ERROR: sku '" . $sku . "' attribute_set: '" . $attributeSetName . "' doesn't exist";
                $this->addMessageAndLog($message, "error");
            } elseif ($attributeSetId !== $foundAttributeSetId) {
                $attributeSetId = $foundAttributeSetId;
            }
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
     * Gets store information by ID
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
     * Imports/updates product data
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param array $productData
     * @param int $storeId
     * @return bool
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function importData(
        \Magento\Catalog\Model\Product $product,
        array $productData,
        int $storeId
    ): bool {
        $sku = $product->getSku();
        $importedAttributes = [];
        try {
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
                    if (!in_array($attrCode, ['attribute_set_id', 'tax_class_id', 'visibility', 'status'])) {
                        // Handle select/dropdown attributes
                        $attribute = $this->getAttribute($attrCode);
                        if (!$attribute) {
                            continue;
                        }
                        $frontendInput = $attribute->getData("frontend_input");
                        if ($frontendInput === 'select') {
                            if (!$attrValue = $this->getAttributeValue($attrCode, (string) $attrValue)) {
                                continue;
                            }
                        } elseif ($frontendInput === 'multiselect') {
                            if (is_string($attrValue)) {
                                $attrValue = array_map('trim', explode(',', $attrValue));
                            }
                            if (!$attrValue = $this->getMultiselectAttributeValue($attrCode, (array) $attrValue)) {
                                continue;
                            }
                        }
                    }
                    $currentValue = $product->getData($attrCode);
                    if ($frontendInput === 'multiselect' && !empty($currentValue)) {
                        $currentValue = explode(',', $currentValue);
                        sort($currentValue);
                        $currentValue = implode(',', $currentValue);
                    }
                    if ($currentValue != $attrValue) {
                        $product->setData($attrCode, $attrValue);
                        $importedAttributes[$attrCode] = $attrValue;
                        /*if (!$this->isNewProduct) {
                            $this->productResource->saveAttribute($product, $attrCode);
                        }*/
                    }
                }
            }

            // Save the product if it's new or if any attribute was changed
            if ($this->isNewProduct) {
                $message = "REQUEST: sku '" . $sku . "' " .
                    json_encode(array_merge($productData['default'], $importedAttributes));
                $this->log($message);
                $product->save();
                // $this->productRepositoryInterface->save($product);
                $message = "SAVED: sku '" . $sku . "' - product id <" . $product->getId() . "> saved successfully";
                $this->addMessageAndLog($message, "success");
            } elseif (count($importedAttributes)) {
                    $message = "REQUEST: sku '" . $sku . "' - product id <" .
                        $product->getId() . "> : " . json_encode($importedAttributes);
                    $this->log($message);
                    $product->save();
                    // Update the product updated_at
                    // $this->updateProductUpdatedAt((int) $product->getId());
                    $message = "SAVED: sku '" . $sku . "' - product id <" . $product->getId() . "> saved successfully";
                    $this->addMessageAndLog($message, "success");
            } else {
                $message = "SKIP: sku '" . $sku . "' - product id <" . $product->getId() . "> no data was changed";
                $this->addMessageAndLog($message, "success");
            }
        } catch (\Exception $e) {
            $message = "ERROR: sku '" . $sku . "' - product id <" . $product->getId() . "> " . $e->getMessage();
            $this->addMessageAndLog($message, "error");
        }

        // Validate product ID existence before proceeding to complex operations
        $productId = (int) $product->getId();
        if (!$productId) {
            $message = "ERROR: sku '" . $sku . "' product ID missing";
            $this->addMessageAndLog($message, "error");
        }

        // import stock
        try {
            if (isset($productData['stock']['qty']) && $productData['stock']['qty'] !== null) {
                $stockData = $productData['stock'];
                $stockUpdateData = [];
                $stockItem = $this->stockRegistryInterface->getStockItem($productId);
                if (!$stockItem->getId()) {
                    $stockUpdateData = [
                        'qty' => 0,
                        'is_in_stock' => 0
                    ];
                } else {
                    $oldQty = (int) $stockItem->getQty();
                    $isInStock = (bool) $stockItem->getData('is_in_stock');
                    $currentMinSaleQty = (int) $stockItem->getData('min_sale_qty');
                    $minCartQty = isset($stockData['min_sale_qty']) ? (int) $stockData['min_sale_qty'] : null;
                    $newQty = (int) $stockData['qty'];
                    $finalQtyToSave = $newQty;
                    // Inventory quantity buffering
                    if (isset($stockData['buffer_qty'])) {
                        $bufferInput = trim((string) $stockData['buffer_qty']);
                        $operator = null;
                        $bufferValue = 0;
                        if (preg_match('/^([+-])(\d+)$/', $bufferInput, $matches)) {
                            $operator = $matches[1];
                            $bufferValue = (int) $matches[2];
                        } elseif (is_numeric($bufferInput) && $bufferInput > 0) {
                            $operator = '-';
                            $bufferValue = (int) $bufferInput;
                        }
                        if ($bufferValue > 0 && !empty($operator)) {
                            $oldSetQty = $finalQtyToSave;
                            if ($operator === "-") {
                                $finalQtyToSave = max(0, $finalQtyToSave - $bufferValue);
                            } elseif ($operator === "+") {
                                $finalQtyToSave = $finalQtyToSave + $bufferValue;
                            }
                            $message = "BUFFER QTY: sku: '{$sku}' - product id <{$productId}> : " .
                                "Buffer '{$bufferInput}' from {$oldSetQty} to {$finalQtyToSave}";
                            $this->addMessageAndLog($message, "success", "quantity");
                        }
                    }
                    // Stock backorder configuration
                    if (isset($stockData['backorders'])) {
                        $backordersValue = (int) $stockData['backorders'];
                        if (in_array($backordersValue, [0, 1, 2])) {
                            $currentBackorders = (int) $stockItem->getData('backorders');
                            $currentUseConfig = (int) $stockItem->getData('use_config_backorders');
                            if ($currentBackorders !== $backordersValue) {
                                $stockUpdateData['backorders'] = $backordersValue;
                                if ($currentUseConfig !== 0) {
                                    $stockUpdateData['use_config_backorders'] = 0;
                                }
                                $message = "SET BACKORDERS: sku: '{$sku}' - product id <{$productId}> : " .
                                    "Set backorders from {$currentBackorders} to {$backordersValue}";
                                $this->addMessageAndLog($message, "success", "quantity");
                            } elseif ($currentUseConfig !== 0) {
                                $stockUpdateData['use_config_backorders'] = 0;
                                $message = "SET BACKORDERS: sku: '{$sku}' - product id <{$productId}> : " .
                                    "Forced backorders {$backordersValue} by disabling 'Use Config Settings'";
                                $this->addMessageAndLog($message, "success", "quantity");
                            }
                        } else {
                            $message = "SET BACKORDERS: sku: '{$sku}' - product id <{$productId}> : Invalid backorders value " .
                                "'{$stockData['backorders']}'. Allowed values are 0, 1, 2";
                            $this->addMessageAndLog($message, "error", "quantity");
                        }
                    }
                    if ($finalQtyToSave > 0) {
                        $setInStock = !empty($stockData['set_in_stock']);
                        if ($setInStock && !$isInStock) {
                            $stockUpdateData['is_in_stock'] = 1;
                        }
                    } elseif ($finalQtyToSave <= 0) {
                        $setOutStock = !empty($stockData['set_out_stock']);
                        if ($setOutStock && $isInStock) {
                            $stockUpdateData['is_in_stock'] = 0;
                        }
                    }
                    if ($oldQty !== $finalQtyToSave) {
                        $stockUpdateData['qty'] = $finalQtyToSave;
                        $stockUpdateData['old_qty'] = $oldQty;
                    }
                    if ($minCartQty && $currentMinSaleQty !== $minCartQty) {
                        $stockUpdateData['min_sale_qty'] = $minCartQty;
                    }
                }
                // Save stock data
                if (!empty($stockUpdateData)) {
                    $product->setStockData($stockUpdateData);
                    $product->save();
                    $message = "SAVED QTY: sku: '{$sku}' - product id <{$productId}> : " . json_encode($stockUpdateData);
                    $this->addMessageAndLog($message, "success", "quantity");
                } else {
                    $message = "SKIP QTY: sku '{$sku}' - product id <{$productId}> no data was changed";
                    $this->addMessageAndLog($message, "success", "quantity");
                }
            }
        } catch (\Exception $e) {
            $message = "ERROR QTY: sku '{$sku}' - product id <{$productId} " . $e->getMessage();
            $this->addMessageAndLog($message, "error", "quantity");
        }

        // import related, up-sell, and cross-sell products
        try {
            $linkTypes = [
                'related' => '_related_skus',
                'upsell' => '_upsell_skus',
                'crosssell' => '_crosssell_skus'
            ];
            $hasLinkData = false;
            foreach ($linkTypes as $attributeCode) {
                if (!empty($productData['attributes'][$attributeCode])) {
                    $hasLinkData = true;
                    break;
                }
            }
            if ($hasLinkData) {
                $updatedSkus = [];
                $existingLinksBySkuAndType = [];
                $productLinks = $product->getProductLinks();
                foreach ($productLinks as $link) {
                    $existingLinksBySkuAndType[$link->getLinkType()][$link->getLinkedProductSku()] = true;
                }
                foreach ($linkTypes as $linkType => $attributeCode) {
                    if (!empty($productData['attributes'][$attributeCode])) {
                        $skusToSet = array_unique(
                            array_map('trim', explode(',', (string) $productData['attributes'][$attributeCode]))
                        );
                        $newSkus = [];
                        foreach ($skusToSet as $newSku) {
                            if (!empty($newSku) && !isset($existingLinksBySkuAndType[$linkType][$newSku])) {
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
                    $product->setProductLinks($productLinks);
                    $product->save();
                    foreach ($updatedSkus as $linkType => $skus) {
                        $message = "SET " . $linkType . ": sku '" . $sku . "' - product id <" .
                            $productId . "> : " . json_encode($skus);
                        $this->log($message);
                    }
                }
            }
        } catch (\Exception $e) {
            $message = "ERROR: sku '" . $sku . "' - product id <" . $productId . "> " . $e->getMessage();
            $this->addMessageAndLog($message, "error");
        }

        // import categories
        try {
            $categoryIds = !empty($productData['category_ids']) ? $productData['category_ids'] : [];
            if (!is_array($categoryIds)) {
                $categoryIds = [];
            }
            $currentCategoryIds = $product->getCategoryIds();
            // Vegan category assignment
            if (isset($productData['custom']['assign_vegan_category'])) {
                $assignVeganCategory = (bool) $productData['custom']['assign_vegan_category'];
                $veganCategory = !empty($productData['custom']['vegan_category'])
                    ? (int) $productData['custom']['vegan_category']
                    : 0;
                if ($veganCategory > 0) {
                    if ($assignVeganCategory === true) {
                        if (!in_array($veganCategory, $categoryIds)) {
                            $categoryIds[] = $veganCategory;
                            if (!in_array($veganCategory, $currentCategoryIds)) {
                                $message = "SET Vegan category: sku: '" . $sku . "' - product id <" . $productId .
                                    "> to category ID " . $veganCategory;
                                $this->addMessageAndLog($message, "success", "category");
                            }
                        }
                    } elseif ($assignVeganCategory === false) {
                        $categoryIds = array_diff($categoryIds, [$veganCategory]);
                        if (in_array($veganCategory, $currentCategoryIds)) {
                            $this->categoryLinkRepository->deleteByIds($veganCategory, $sku);
                            $product->setCategoryIds($categoryIds);
                            $message = "REMOVE Vegan category: sku: '" . $sku . "' - product id <" . $productId .
                                "> from category ID " . $veganCategory;
                            $this->addMessageAndLog($message, "success", "category");
                        }
                    }
                }
            }
            // Clearance category assignment
            $assignClearanceCategory = !empty($productData['custom']['assign_clearance_category']);
            if ($assignClearanceCategory) {
                $clearanceCategory = !empty($productData['custom']['clearance_category'])
                    ? (string) $productData['custom']['clearance_category']
                    : '';
                if ($clearanceCategory !== '') {
                    $clearanceCatIds = $this->categoryHelper->processCategoryTree(
                        $clearanceCategory,
                        $storeId,
                        $this->allowCreateCategory
                    );
                    if (empty($clearanceCatIds)) {
                        $message = "WARN: category '" . $clearanceCategory . "' not found";
                        $this->results["response"]["category"]["warn"][] = $message;
                        $this->log($message);
                    } else {
                        $newCatIds = array_diff($clearanceCatIds, $categoryIds);
                        $categoryIds = array_merge($categoryIds, $newCatIds);
                        $newlyAssignedIds = array_diff($newCatIds, $currentCategoryIds);
                        foreach ($newlyAssignedIds as $clearanceCatId) {
                            $message = "SET Clearance category: sku: '" . $sku . "' - product id <" . $productId .
                                "> to category ID " . $clearanceCatId;
                            $this->addMessageAndLog($message, "success", "category");
                        }
                    }
                }
            }
            // Set product categories
            if (!empty($categoryIds)) {
                $updateMethod = !empty($productData['custom']['category_update_method'])
                    ? (int) $productData['custom']['category_update_method']
                    : 1;
                $this->setCategories($product, $categoryIds, $updateMethod);
            }
        } catch (\Exception $e) {
            $message = "ERROR category: sku '" . $sku . "' - product id <" .
                $productId . "> " . $e->getMessage();
            $this->addMessageAndLog($message, "error", "category");
        }

        // import images
        try {
            $imagesToImport = $productData['images'] ?? [];
            if (!empty($imagesToImport)) {
                $newIoImages = $this->serializeImageArray($imagesToImport);
                $currentIoImages = (string) $product->getData($this->ioImagesField);
                $isDeleteRequested = !empty($productData['custom']['delete_existing_product_images']);
                $isImageChangeNeeded = ($currentIoImages !== $newIoImages);
                if ($isImageChangeNeeded) {
                    if ($isDeleteRequested) {
                        $this->deleteExistingProductImages($product);
                    }
                    $product = $this->productFactory->create()->load($productId);
                    if ($product && $product->getId()) {
                        $totalImagesChanges = $this->importImages($product, $imagesToImport);
                        $product->setData($this->ioImagesField, $newIoImages);
                        if ($totalImagesChanges > 0 || $product->hasDataChanges()) {
                            $product->save();
                            $message = "SAVED: sku '" . $product->getSku() . "' - product id <" .
                                $product->getId() . "> saved successfully";
                            $this->addMessageAndLog($message, "success", "image");
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $message = "ERROR: sku '" . $sku . "' - product id <" .
                $productId . "> set image: " . $e->getMessage();
            $this->addMessageAndLog($message, "error", "image");
        }

        // import variations
        try {
            $variationData = $productData['variation'];
            $groupedData = $productData['grouped'];
            $isConfigurableProcessingNeeded = (
                $variationData['is_in_relationship'] && !$variationData['is_parent']
            );
            $isGroupedProcessingNeeded = !empty($groupedData);
            if (!$isConfigurableProcessingNeeded && !$isGroupedProcessingNeeded) {
                return false;
            }
            $product = $this->productFactory->create()->setStoreId($storeId)->load($productId);
            if ($product->getTypeId() != 'grouped' && $isConfigurableProcessingNeeded) {
                $parentSku = $variationData['parent_sku'];
                $parentId = $this->productFactory->create()->getIdBySku($parentSku);
                if (!$parentId) {
                    $message = "WARN: NOT PARENT '" . $parentSku . "'";
                    $this->addMessageAndLog($message, "warn", "variation");
                    return false;
                }
                $parent = $this->productFactory->create()->load($parentId);
                $superAttrCodes = $variationData['super_attribute'];
                if (!$superAttrCodes) {
                    $message = "WARN: ADD CHILD '" . $sku . "' - product id <" .
                        $productId . "> to PARENT '" . $parentSku . "': invalid supper attributes";
                    $this->addMessageAndLog($message, "warn", "variation");
                    return false;
                }
                $superAttrCodes = explode(',', (string) $superAttrCodes);
                sort($superAttrCodes);
                if ($parent->getTypeId() != 'configurable') {
                    $message = "WARN: INVALID PARENT '" . $parentSku . "'";
                    $this->addMessageAndLog($message, "warn", "variation");
                    return false;
                }
                $allChildrenIds = $this->configurableResourceModel->getChildrenIds($parentId);
                $childrenProductIds = !empty($allChildrenIds) ? current($allChildrenIds) : [];
                $needChangConfigurable = false;
                $hasParentError = false;
                if (!in_array((string) $productId, $childrenProductIds)) {
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
                    // Assign position to super attributes
                    foreach ($superAttrCodes as $superAttrCode) {
                        $superAttrPositions[$superAttrCode] = count($superAttrPositions) + 1;
                    }
                    $configurableAttributes = $parent->getExtensionAttributes();
                    $configurableOptions = $configurableAttributes->getConfigurableProductOptions();
                    // Process existing configurable options
                    foreach ($configurableOptions as $_configurableOption) {
                        $attrCode = $_configurableOption->getProductAttribute()->getAttributeCode();
                        if (!isset($superAttrPositions[$attrCode])) {
                            $message = "WARN: ADD CHILD '" . $sku . "' - product id <" .
                                $productId . "> to PARENT '" . $parentSku . "': attribute '" .
                                $attrCode . "' is not mapped";
                            $this->addMessageAndLog($message, "warn", "variation");
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
                    // Add new/updated configurable options from the child product
                    foreach ($superAttrCodes as $superAttrCode) {
                        if (!isset($superAttrPositions[$superAttrCode])) {
                            $message = "WARN: ADD CHILD '" . $sku . "' - product id <" .
                                $productId . "> to PARENT '" . $parentSku . "': attribute '" .
                                $superAttrCode . "' is not mapped";
                            $this->addMessageAndLog($message, "warn", "variation");
                            $hasParentError = true;
                            break;
                        }
                        if (!$product->getData($superAttrCode)) {
                            $message = "WARN: ADD CHILD '" . $sku . "' - product id <" .
                                $productId . "> to PARENT '" . $parentSku . "': attribute '" .
                                $superAttrCode . "' doesn't have value";
                            $this->addMessageAndLog($message, "warn", "variation");
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
                        // Update configurable options and link children
                        if (!in_array((string) $productId, $childrenProductIds)) {
                            $childrenProductIds[] = (string) $productId;
                        }
                        $childrenProductIds = array_unique($childrenProductIds);
                        $childrenProductIds = array_values($childrenProductIds);
                        $optionsFactory = $this->productOptionFactory;
                        $configurableOptions = $optionsFactory->create($configurableAttrData);
                        $configurableAttributes = $parent->getExtensionAttributes();
                        $configurableAttributes->setConfigurableProductOptions(
                            $configurableOptions
                        );
                        $configurableAttributes->setConfigurableProductLinks($childrenProductIds);
                        $parent->setExtensionAttributes($configurableAttributes);
                        $parent->save();
                        // $this->productRepositoryInterface->save($parent);
                        $message = "ADDED CHILD '" . $sku . "' - product id <" .
                            $productId . "> to PARENT '" . $parentSku . "'";
                        $this->addMessageAndLog($message, "success", "variation");
                    }
                }
            } elseif ($product->getTypeId() == 'grouped' && $isGroupedProcessingNeeded) {
                // Associate simple products to the grouped parent
                if (empty($groupedData['is_parent'])) {
                    $message = "WARN: Grouped parent '" . $sku . "' requires 'is_parent' set to true";
                    $this->addMessageAndLog($message, "warn", "variation");
                    return false;
                }
                if (empty($groupedData['child_sku'])) {
                    $message = "WARN: Grouped parent '" . $sku . "' has no child SKUs provided";
                    $this->addMessageAndLog($message, "warn", "variation");
                    return false;
                }
                $updatedChildSkus = [];
                $groupedLinkType = 'associated';
                $childrenSkus = array_map('trim', explode(',', (string) $groupedData['child_sku']));
                $linksToSave = $product->getProductLinks() ?: [];
                $existingAssociatedSkus = [];
                foreach ($linksToSave as $link) {
                    if ($link->getLinkType() == $groupedLinkType) {
                        $existingAssociatedSkus[$link->getLinkedProductSku()] = true;
                    }
                }
                $newSkus = [];
                foreach ($childrenSkus as $childrenSku) {
                    if (!empty($childrenSku) && !isset($existingAssociatedSkus[$childrenSku])) {
                        $newSkus[] = $childrenSku;
                    }
                }
                if (!empty($newSkus)) {
                    $productCollection = $this->productFactory->create()
                        ->getCollection()
                        ->addAttributeToSelect(['sku', 'type_id'])
                        ->addAttributeToFilter('sku', ['in' => $newSkus]);

                    $productTypes = [];
                    foreach ($productCollection as $_product) {
                        $productTypes[$_product->getSku()] = $_product->getTypeId();
                    }
                    foreach ($newSkus as $childrenSku) {
                        if (isset($productTypes[$childrenSku])) {
                            $productLink = $this->productLink->create()
                                ->setSku($sku)
                                ->setLinkType($groupedLinkType)
                                ->setLinkedProductSku($childrenSku)
                                ->setLinkedProductType($productTypes[$childrenSku])
                                ->setPosition(1)
                                ->setQty(1);

                            $linksToSave[] = $productLink;
                            $updatedChildSkus[] = $childrenSku;
                        } else {
                            $this->log("WARN: Grouped child sku '" . $childrenSku . "' not found");
                        }
                    }
                }
                if (!empty($updatedChildSkus)) {
                    $product->setProductLinks($linksToSave);
                    $product->save();
                    // $this->productRepositoryInterface->save($product);
                    foreach ($updatedChildSkus as $updatedChildSku) {
                        $message = "ADDED CHILD '" . $updatedChildSku . "' to grouped parent '" . $sku . "'";
                        $this->addMessageAndLog($message, "success", "variation");
                    }
                }
            }
        } catch (\Exception $e) {
            $message = "ERROR: Failed to set variation for sku '" . $sku . "' - product id <" .
                $productId . "> " . $e->getMessage();
            $this->addMessageAndLog($message, "error", "variation");
        }
        return true;
    }

    /**
     * Set categories for product, allowing selection of update method
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param array $categoryIds
     * @param int $updateMethod
     * @return void
     */
    public function setCategories(
        \Magento\Catalog\Model\Product $product,
        array $categoryIds,
        int $updateMethod = 1
    ): void {
        $categoryIds = array_map('intval', $categoryIds);
        $categoryIds = array_filter(array_unique($categoryIds));
        sort($categoryIds);

        $oldCategoryIds = $product->getCategoryIds();
        $oldCategoryIds = array_map('intval', $oldCategoryIds);
        sort($oldCategoryIds);

        if ($oldCategoryIds !== $categoryIds) {
            $updateMethod = (int) $updateMethod;
            $methodUsed = '';
            switch ($updateMethod) {
                case 1:
                    $product->setCategoryIds($categoryIds);
                    $product->save();
                    $methodUsed = 'Model Save (1)';
                    break;
                case 2:
                    $this->categoryLinkManagementInterface->assignProductToCategories(
                        $product->getSku(),
                        $categoryIds
                    );
                    $product->setCategoryIds($categoryIds);
                    $methodUsed = 'API Assign (2)';
                    break;
                case 3:
                    $this->updateCategories((int) $product->getId(), $oldCategoryIds, $categoryIds);
                    $product->setCategoryIds($categoryIds);
                    $methodUsed = 'Direct DB (3)';
                    break;
                default:
                    $product->setCategoryIds($categoryIds);
                    $product->save();
                    $methodUsed = 'Model Save (Default)';
                    break;
            }
            $message = "SET category: sku '" . $product->getSku() . "' - product id <" .
                $product->getId() . "> - New IDs: [" . implode(",", $categoryIds) . "] - Old IDs: [" .
                implode(",", $oldCategoryIds) . "] - Method: " . $methodUsed;
            $this->addMessageAndLog($message, "success", "category");
        }
    }

    /**
     * Updates and synchronizes product categories
     *
     * @param int $productId
     * @param array $oldCategoryIds
     * @param array $categoryIds
     * @return void
     */
    public function updateCategories(
        int $productId,
        array $oldCategoryIds,
        array $categoryIds
    ): void {
        $dbw = $this->resourceConnection->getConnection('core_write');
        $productCategoryTable = $this->resourceConnection->getTableName('catalog_category_product');
        $insert = array_diff($categoryIds, $oldCategoryIds);
        $delete = array_diff($oldCategoryIds, $categoryIds);
        if (!empty($insert)) {
            $data = array();
            foreach ($insert as $categoryId) {
                $data[] = [
                    'category_id' => (int) $categoryId,
                    'product_id' => $productId,
                    'position' => 1
                ];
            }
            if ($data) {
                $dbw->insertMultiple($productCategoryTable, $data);
            }
        }
        if (!empty($delete)) {
            $where = [
                'product_id = ?'  => $productId,
                'category_id IN (?)' => $delete
            ];
            $dbw->delete($productCategoryTable, $where);
        }
    }

    /**
     * Converts the image array into the string format (ITEMIMAGEURLx=...)
     *
     * @param array $imageArray
     * @return string
     */
    public function serializeImageArray(array $imageArray): string
    {
        $pairs = [];
        foreach ($imageArray as $key => $value) {
            $pairs[] = trim($key) . '=' . trim($value);
        }
        return implode(',', $pairs);
    }

    /**
     * Delete all existing media gallery entries (images) for a given product
     *
     * @param @param \Magento\Catalog\Model\Product $product
     * @return bool
     */
    public function deleteExistingProductImages(
        \Magento\Catalog\Model\Product $product
    ): bool {
        if (!$product || !$product->getId()) {
            return false;
        }
        $existingMediaGalleryEntries = $product->getMediaGalleryEntries();
        if ($existingMediaGalleryEntries && count($existingMediaGalleryEntries)) {
            $product->setMediaGalleryEntries([]);
            $product->setData($this->ioImagesField, '');
            try {
                $this->productRepositoryInterface->save($product);
                $message = "Delete existing images: sku '" . $product->getSku() . "' - product id <" .
                $product->getId() . "> were deleted successfully";
            $this->addMessageAndLog($message, "success", "custom");
                return true;
            } catch (\Exception $e) {
                $message = "ERROR: sku '" . $product->getSku() . "' - product id <" .
                    $product->getId() . "> Failed to delete existing images: " . $e->getMessage();
                $this->addMessageAndLog($message, "error", "custom");
                return false;
            }
        }
        return false;
    }

    /**
     * Prepare Product Data for Import
     *
     * @param array $attributeInfo
     * @param \Magento\Catalog\Model\Product $product
     * @param string[] $variationInfo
     * @param string[] $groupedInfo
     * @param string[] $stockInfo
     * @param string[] $imageInfo
     * @param string[] $customInfo
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
        array $customInfo,
        int $storeId
    ): array {
        $result = [
            'attributes' => [],
            'category_ids' => [],
            'variation' => $variationInfo,
            'grouped' => $groupedInfo,
            'stock' => $stockInfo,
            'images' => $imageInfo,
            'custom' => $customInfo
        ];
        $categoryIdsToSet = [];
        $productAttributeSetId = (int) $product->getData("attribute_set_id");
        $attrCodesInSet = $this->getAttributeCodesInSet($productAttributeSetId);
        $ignoreCustomAttributes = ['qty', 'is_in_stock', 'min_sale_qty', 'category_ids', 'category_name'];

        foreach ($attributeInfo as $attrCode => $attrValue) {
            if ($attrValue === null || $attrValue === "") {
                continue;
            }
            if (in_array($attrCode, $ignoreCustomAttributes)) {
                continue;
            }
            if ($product->getResource()->getAttribute($attrCode)
                && !in_array($attrCode, $attrCodesInSet)) {
                continue;
            }
            $processedValue = $this->processAttributeValue($attrCode, $attrValue, $product, $storeId);
            if ($processedValue === null) {
                continue;
            }
            if ($attrCode === 'weight' && floatval($processedValue) <= 0) {
                continue;
            }
            if ($attrCode === "special_from_date"
                && $product->getData("special_price")
                && empty($processedValue)) {
                continue;
            }
            $result['attributes'][$attrCode] = $processedValue;
        }

        // Set 'price' to 0 for the parent product if it's currently null or not present in import attributes
        if ($result['variation']['is_parent'] && $product->getPrice() === null
            && (!isset($result['attributes']['price']) || $result['attributes']['price'] === null)) {
            $result['attributes']['price'] = 0;
        }

        $this->processCategoryAttributes($attributeInfo, $categoryIdsToSet, $storeId);
        $result['category_ids'] = array_unique(array_map('intval', array_filter($categoryIdsToSet)));

        return $result;
    }

    /**
     * Process Attribute Value by Code
     *
     * @param string $attrCode
     * @param mixed $attrValue
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return mixed
     */
    public function processAttributeValue(string $attrCode, $attrValue, \Magento\Catalog\Model\Product $product, int $storeId)
    {
        switch ($attrCode) {
            case 'attribute_set':
                return $this->getAttributeSetIdByName((string) $attrValue);
            case 'tax_class':
                return $this->getTaxClassIdByName((string) $attrValue);
            case 'visibility':
                return $this->getVisibility((string) $attrValue);
            case 'status':
                return $this->getStatus((string) $attrValue);
            case 'weight':
                return floatval($attrValue);
            default:
                return $attrValue;
        }
    }

    /**
     * Get Attribute Codes in Set
     *
     * @param int $attributeSetId
     * @return array
     */
    public function getAttributeCodesInSet(int $attributeSetId): array
    {
        if (isset($this->attributeCodesInSetCache[$attributeSetId])) {
            return $this->attributeCodesInSetCache[$attributeSetId];
        }
        $attrsInSet = $this->productAttributeManagement->getAttributes($attributeSetId);
        $attrCodesInSet = ['type_id'];
        foreach ($attrsInSet as $attrInSet) {
            $attrCodesInSet[] = $attrInSet->getAttributeCode();
        }
        $this->attributeCodesInSetCache[$attributeSetId] = $attrCodesInSet;
        return $attrCodesInSet;
    }

    /**
     * Get Attribute Set ID by Name
     *
     * @param string $attributeSetName
     * @return int|null
     */
    public function getAttributeSetIdByName(string $attributeSetName): ?int
    {
        $attributeSetName = trim($attributeSetName);
        if (empty($attributeSetName)) {
            return null;
        }
        if (isset($this->attributeSetCache[$attributeSetName])) {
            return $this->attributeSetCache[$attributeSetName];
        }
        $entityTypeId = $this->productResource->getEntityType()->getEntityTypeId();
        $attributeSet = $this->entityAttributeSetFactory->create()
            ->getCollection()
            ->setEntityTypeFilter($entityTypeId)
            ->addFieldToFilter('attribute_set_name', $attributeSetName)
            ->getFirstItem();
        $attributeSetId = (int) $attributeSet->getAttributeSetId();
        if ($attributeSetId) {
            $this->attributeSetCache[$attributeSetName] = $attributeSetId;
            return $attributeSetId;
        }
        return null;
    }

    /**
     * Get Tax Class ID by Name
     *
     * @param string $taxClassName
     * @return int|null
     */
    public function getTaxClassIdByName(string $taxClassName): ?int
    {
        $taxClassName = trim($taxClassName);
        if (empty($taxClassName)) {
            return null;
        }
        if ($taxClassName === 'None') {
            return self::TAX_CLASS_NONE;
        }
        if (isset($this->taxClassCache[$taxClassName])) {
            return $this->taxClassCache[$taxClassName];
        }
        $taxClass = $this->classModelFactory->create()
            ->getCollection()
            ->addFieldToFilter('class_type', \Magento\Tax\Model\ClassModel::TAX_CLASS_TYPE_PRODUCT)
            ->addFieldToFilter('class_name', $taxClassName)
            ->getFirstItem();
        $taxClassId = (int) $taxClass->getClassId();
        if ($taxClassId) {
            $this->taxClassCache[$taxClassName] = $taxClassId;
            return $taxClassId;
        }
        return null;
    }

    /**
     * Get Visibility
     *
     * @param string $visibility
     * @return int
     */
    public function getVisibility(string $visibility): int
    {
        $trimmedVisibility = trim($visibility);
        if (is_numeric($trimmedVisibility)) {
            $id = (int) $trimmedVisibility;
            if (in_array($id, [
                self::VISIBILITY_NOT_VISIBLE,
                self::VISIBILITY_IN_CATALOG,
                self::VISIBILITY_IN_SEARCH,
                self::VISIBILITY_CATALOG_SEARCH
            ])) {
                return $id;
            }
        }
        return match ($trimmedVisibility) {
            'Catalog, Search' => self::VISIBILITY_CATALOG_SEARCH,
            'Search' => self::VISIBILITY_IN_SEARCH,
            'Catalog' => self::VISIBILITY_IN_CATALOG,
            'Not Visible Individually' => self::VISIBILITY_NOT_VISIBLE,
            default => self::VISIBILITY_CATALOG_SEARCH,
        };
    }

    /**
     * Get Status ID by Name or Value
     *
     * @param string $status
     * @return int
     */
    public function getStatus(string $status): int
    {
        $statusValue = strtolower(trim($status));
        return match ($statusValue) {
            'disabled', 'false', '2' => self::STATUS_DISABLED,
            default => self::STATUS_ENABLED,
        };
    }

    /**
     * Handle Category Attributes
     *
     * @param array $attributeInfo
     * @param array $categoryIdsToSet
     * @param int $storeId
     * @return void
     */
    public function processCategoryAttributes(array $attributeInfo, array &$categoryIdsToSet, int $storeId): void
    {
        if (!empty($attributeInfo['category_ids'])) {
            $ids = array_map('trim', explode(',', (string) $attributeInfo['category_ids']));
            $categoryIdsToSet = array_merge($categoryIdsToSet, $ids);
        }
        if (!empty($attributeInfo['category_name'])) {
            $foundCatIds = $this->categoryHelper->processCategoryTree(
                (string) $attributeInfo['category_name'],
                $storeId,
                $this->allowCreateCategory
            );
            if (empty($foundCatIds)) {
                $message = "WARN: category '" . $attributeInfo['category_name'] . "' not found";
                $this->results["response"]["category"]["warn"][] = $message;
                $this->log($message);
            } else {
                $categoryIdsToSet = array_merge($categoryIdsToSet, $foundCatIds);
            }
        }
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
            $addedImagesCount = $this->imageHelper->populateProductImage($product, $imageList, $this->ioImagesField);
            return $addedImagesCount;
        }
        return 0;
    }

    /**
     * Get attribute data
     *
     * @param string $attrCode
     * @return ProductAttributeInterface|null
     */
    public function getAttribute(string $attrCode): ?ProductAttributeInterface
    {
        return $this->productAttributeHelper->getAttribute($attrCode);
    }

    /**
     * Get attribute value
     *
     * @param string $attributeCode
     * @param string $optionLabel
     * @return string|int|null
     */
    public function getAttributeValue(
        string $attributeCode,
        string $optionLabel
    ): string|int|null {
        $attrValue = $this->productAttributeHelper->getAttributeOptionValue($attributeCode, $optionLabel);
        if (null === $attrValue) {
            $message = "ERROR: Could not get or create attribute option value for attribute " .
                "'{$attributeCode}' with label '{$optionLabel}'";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log($message);
        }
        return $attrValue;
    }

    /**
     * Get the comma-separated string of Option IDs for a 'multiselect' attribute
     *
     * @param string $attrCode
     * @param array $optionLabels
     * @return string|null
     */
    public function getMultiselectAttributeValue(string $attrCode, array $optionLabels): ?string
    {
        $optionIds = [];
        $hasError = false;
        foreach ($optionLabels as $optionLabel) {
            $optionLabel = trim((string)$optionLabel);
            if (empty($optionLabel)) {
                continue;
            }
            $optionId = $this->productAttributeHelper->getAttributeOptionValue($attrCode, $optionLabel);
            if ($optionId) {
                $optionIds[] = (string) $optionId;
            } else {
                $message = "ERROR: Could not get or create option ID for multiselect attribute " .
                    "'{$attrCode}' with label '{$optionLabel}'";
                $this->results["response"]["data"]["error"][] = $message;
                $this->log($message);
                $hasError = true;
            }
        }
        if ($hasError && empty($optionIds)) {
            return null;
        }
        sort($optionIds);
        return implode(',', $optionIds);
    }

    /**
     * Update product updated_at after saving an attribute
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
        $sections = ['data', 'quantity', 'category', 'image', 'variation', 'custom'];
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
     * @param string $section
     * @param int $logLevel
     * @return void
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function addMessageAndLog(
        string $message,
        string $type = "success",
        string $section = "data",
        int $logLevel = 1
    ): void {
        $this->results["response"][$section][$type][] = $message;
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
     * Logs a message
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
