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

use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;

class StockManagement implements \WiseRobot\Io\Api\StockManagementInterface
{
    /**
     * @var Zend_Log
     */
    public $logger;
    /**
     * @var string
     */
    public $logFile = "wr_io_stock_import.log";
    /**
     * @var array
     */
    public array $results = [];
    /**
     * @var Filesystem
     */
    public $filesystem;
    /**
     * @var ProductFactory
     */
    public $productFactory;
    /**
     * @var ProductCollectionFactory
     */
    public $productCollectionFactory;
    /**
     * @var StockRegistryInterface
     */
    public $stockRegistryInterface;
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;
    /**
     * @var AttributeRepositoryInterface
     */
    public $attributeRepositoryInterface;
    /**
     * @var ResourceConnection
     */
    public $resourceConnection;
    /**
     * @var ModuleManager
     */
    public $moduleManager;
    /**
     * @var ObjectManagerInterface
     */
    public $objectManager;

    /**
     * @param Filesystem $filesystem
     * @param ProductFactory $productFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param StockRegistryInterface $stockRegistryInterface
     * @param StoreManagerInterface $storeManager
     * @param AttributeRepositoryInterface $attributeRepositoryInterface
     * @param ResourceConnection $resourceConnection
     * @param ModuleManager $moduleManager
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        Filesystem $filesystem,
        ProductFactory $productFactory,
        ProductCollectionFactory $productCollectionFactory,
        StockRegistryInterface $stockRegistryInterface,
        StoreManagerInterface $storeManager,
        AttributeRepositoryInterface $attributeRepositoryInterface,
        ResourceConnection $resourceConnection,
        ModuleManager $moduleManager,
        ObjectManagerInterface $objectManager
    ) {
        $this->filesystem = $filesystem;
        $this->productFactory = $productFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockRegistryInterface = $stockRegistryInterface;
        $this->storeManager = $storeManager;
        $this->attributeRepositoryInterface = $attributeRepositoryInterface;
        $this->resourceConnection = $resourceConnection;
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
        $this->initializeResults();
        $this->initializeLogger();
    }

    /**
     * Filter Stock Data
     *
     * @param int $store
     * @param string $filter
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getList(
        int $store,
        string $filter = "",
        int $page = 1,
        int $limit = 100
    ): array {
        $storeInfo = $this->getStoreInfo($store);
        $productCollection = $this->createProductCollection($store);
        $this->applySelectAttributes($productCollection);
        $this->applyFilter($productCollection, $filter);
        $this->applySortingAndPaging($productCollection, $page, $limit);
        $result = [];
        $storeName = $storeInfo->getName();
        foreach ($productCollection as $product) {
            $sku = $product->getData("sku");
            if ($sku) {
                $stockData = $this->formatStockData($product);
                if (!empty($stockData)) {
                    $stockData['store'] = $storeName;
                    $result[$sku] = $stockData;
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
                ['*'],
                'stock_id = 1',
                'left'
            );
        return $productCollection;
    }

    /**
     * Apply selected attributes to the product collection
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection
     * @return void
     */
    public function applySelectAttributes(
        \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection
    ): void {
        $productCollection->addAttributeToSelect(['entity_id', 'sku', 'created_at', 'updated_at']);
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
            if (count($condition) !== 2 || empty($condition[0]) || empty($condition[1])) {
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
            $operator = trim($operator);
            if (in_array($operator, ['in', 'nin'])) {
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
            ' eq ',
            ' neq ',
            ' gt ',
            ' gteq ',
            ' lt ',
            ' lteq ',
            ' like ',
            ' nlike ',
            ' in ',
            ' nin ',
            ' null ',
            ' notnull ',
        ];
        foreach ($operators as $operator) {
            if (strpos($string, $operator) !== false) {
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
     * Get Stock Data
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    public function formatStockData(
        \Magento\Catalog\Model\Product $product
    ): array {
        $productSku = $product->getData("sku");
        $qty = (int) $product->getData("qty");
        $minCartQty = (int) $product->getData("min_sale_qty");
        $stockData = [
            'stock_info' => [
                "store_id" => (int) $product->getData("store_id"),
                'product_id' => (int) $product->getData("entity_id"),
                'sku' => $productSku,
                'created_at' => $product->getData("created_at"),
                'updated_at' => $product->getData("updated_at"),
                "qty" => $qty ?: null,
                "min_cart_qty" => $minCartQty ?: null,
            ]
        ];
        if ($this->isMSIEnabled()) {
            $this->populateSourceItemsInfo($stockData, $productSku);
            $this->populateSalableQuantityInfo($stockData, $productSku);
        }
        return $stockData;
    }

    /**
     * Populate Source Items Info
     *
     * @param array $stockData
     * @param string $sku
     * @return void
     */
    public function populateSourceItemsInfo(
        array &$stockData,
        string $sku
    ): void {
        $sourceItemsInfo = [];
        $sourceItems = $this->objectManager->get(
            \Magento\InventoryApi\Api\GetSourceItemsBySkuInterface::class
        )->execute($sku);
        foreach ($sourceItems as $sourceItem) {
            $sourceCode = $sourceItem->getData('source_code');
            try {
                $source = $this->objectManager->get(
                    \Magento\InventoryApi\Api\SourceRepositoryInterface::class
                )->get($sourceCode);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                continue;
            }
            $sourceItemsInfo[] = [
                'source_item_id' => (int) $sourceItem->getData('source_item_id'),
                'source_code' => $sourceCode,
                'source_name' => $source->getName(),
                'quantity' => (int) $sourceItem->getData('quantity'),
                'status' => (int) $sourceItem->getData('status'),
            ];
        }
        if (!empty($sourceItemsInfo)) {
            $stockData['source_items_info'] = $sourceItemsInfo;
        }
    }

    /**
     * Populate Salable Quantity Info
     *
     * @param array $stockData
     * @param string $sku
     * @return void
     */
    public function populateSalableQuantityInfo(
        array &$stockData,
        string $sku
    ): void {
        $salableQuantityInfo = [];
        $salableQuantities = $this->objectManager->get(
            \Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku::class
        )->execute($sku);
        foreach ($salableQuantities as $salableQuantity) {
            $sourceCodes = [];
            $stockId = (int) $salableQuantity['stock_id'];
            $sources = $this->objectManager->get(
                \Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface::class
            )->execute($stockId);
            foreach ($sources as $source) {
                $sourceCodes[] = $source->getSourceCode();
            }
            $salableQuantityInfo[] = [
                'stock_id' => $stockId,
                'stock_name' => $salableQuantity['stock_name'],
                'qty' => (int) $salableQuantity['qty'],
                'manage_stock' => (int) $salableQuantity['manage_stock'],
                'source_codes' => implode(",", $sourceCodes),
            ];
        }
        if (!empty($salableQuantityInfo)) {
            $stockData['salable_quantity_info'] = $salableQuantityInfo;
        }
    }

    /**
     * Check MSI (Multi-Source Inventory) Status
     *
     * @return bool
     */
    public function isMSIEnabled()
    {
        return $this->moduleManager->isEnabled('Magento_Inventory');
    }

    /**
     * Import Stock
     *
     * @param mixed $stockInfo
     * @return array
     */
    public function import(mixed $stockInfo): array
    {
        $this->validateStockInfo($stockInfo);
        $this->getStoreInfo((int) $stockInfo['store_id']);
        $this->importIoStock($stockInfo);
        $this->cleanResponseMessages();
        return $this->results;
    }

    /**
     * Validate Stock Info
     *
     * @param mixed $stockInfo
     * @return void
     */
    public function validateStockInfo(mixed $stockInfo): void
    {
        if (empty($stockInfo) || !is_array($stockInfo)) {
            $this->addMessageAndLog("Field: 'stock_info' is a required field", "error");
        }
        if (empty($stockInfo["store_id"]) || empty($stockInfo["sku"]) || empty($stockInfo["qty"])) {
            $this->addMessageAndLog("Field: 'stock_info' - {'store_id','sku','qty'} data fields are required", "error");
        }
    }

    /**
     * Import Io Stock
     *
     * @param array $stockInfo
     * @return bool
     */
    public function importIoStock(array $stockInfo): bool
    {
        $productId = $this->productFactory->create()->getIdBySku($stockInfo['sku']);
        if (!$productId) {
            $this->addMessageAndLog("Cannot get product id from sku '{$stockInfo['sku']}'", "error");
        }
        $product = $this->productFactory->create()->setStoreId((int) $stockInfo['store_id'])->load($productId);
        if (!$product || !$product->getId()) {
            $this->addMessageAndLog("Cannot load product by id <{$productId}>", "error");
        }
        $this->importStock($product, $stockInfo);
        return true;
    }

    /**
     * Import Stock Data
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param array $stockInfo
     * @return void
     */
    public function importStock(
        \Magento\Catalog\Model\Product $product,
        array $stockInfo
    ): void {
        try {
            $productId = (int) $product->getId();
            $sku = $product->getSku();
            $_qty = (int) $stockInfo['qty'];
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
                $minCartQty = isset($stockInfo['min_sale_qty']) ? (int) $stockInfo['min_sale_qty'] : null;
                if ($_qty > 0 && !$isInStock) {
                    $stockUpdateData['is_in_stock'] = 1;
                } elseif ($_qty <= 0 && $isInStock) {
                    $stockUpdateData['is_in_stock'] = 0;
                }
                if ($oldQty !== $_qty) {
                    $stockUpdateData['qty'] = $_qty;
                    $stockUpdateData['old_qty'] = $oldQty;
                }
                if ($minCartQty && $currentMinSaleQty !== $minCartQty) {
                    $stockUpdateData['min_sale_qty'] = $minCartQty;
                }
            }
            if (!empty($stockUpdateData)) {
                $product->setStockData($stockUpdateData);
                $product->save();
                $message = "SAVED QTY: sku: '{$sku}' - product id <{$productId}> : " . json_encode($stockUpdateData);
                $this->addMessageAndLog($message, "success");
            } else {
                $message = "SKIP QTY: sku '{$sku}' - product id <{$productId}> no data was changed";
                $this->addMessageAndLog($message, "success");
            }
        } catch (\Exception $e) {
            $message = "ERROR QTY: sku '{$sku}' - product id <{$productId} " . $e->getMessage();
            $this->addMessageAndLog($message, "error");
        }
    }

    /**
     * Initialize results structure
     *
     * @return void
     */
    public function initializeResults(): void
    {
        $this->results = [
            "response" => [
                "data" => [
                    "success" => [],
                    "error" => []
                ]
            ]
        ];
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
