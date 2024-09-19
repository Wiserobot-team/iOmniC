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
     * @param Filesystem $filesystem
     * @param ProductFactory $productFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param StockRegistryInterface $stockRegistryInterface
     * @param StoreManagerInterface $storeManager
     * @param AttributeRepositoryInterface $attributeRepositoryInterface
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Filesystem $filesystem,
        ProductFactory $productFactory,
        ProductCollectionFactory $productCollectionFactory,
        StockRegistryInterface $stockRegistryInterface,
        StoreManagerInterface $storeManager,
        AttributeRepositoryInterface $attributeRepositoryInterface,
        ResourceConnection $resourceConnection
    ) {
        $this->filesystem = $filesystem;
        $this->productFactory = $productFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockRegistryInterface = $stockRegistryInterface;
        $this->storeManager = $storeManager;
        $this->attributeRepositoryInterface = $attributeRepositoryInterface;
        $this->resourceConnection = $resourceConnection;
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
                    $result[$sku] = array_merge(['store' => $storeName], $stockData);
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
        $productCollection = $this->productCollectionFactory->create()
            ->addStoreFilter($store)
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
     * Get Stock Data
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    public function formatStockData(
        \Magento\Catalog\Model\Product $product
    ): array {
        $qty = (int) $product->getData("qty");
        $minCartQty = (int) $product->getData("min_sale_qty");
        return [
            'stock_info' => [
                "store_id" => (int) $product->getData("store_id"),
                'product_id' => (int) $product->getData("entity_id"),
                'sku' => $product->getData("sku"),
                'created_at' => $product->getData("created_at"),
                'updated_at' => $product->getData("updated_at"),
                "qty" => $qty ?: null,
                "min_cart_qty" => $minCartQty ?: null
            ]
        ];
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
            $this->addMessageAndLog("Cannot get product ID from SKU {$stockInfo['sku']}", "error");
        }
        $product = $this->productFactory->create()->setStoreId((int) $stockInfo['store_id'])->load($productId);
        if (!$product || !$product->getId()) {
            $this->addMessageAndLog("Cannot load product by ID {$productId}", "error");
        }
        $this->importStock($product, $stockInfo);
        return true;
    }

    /**
     * Import Stock Data
     *
     * @param @param \Magento\Catalog\Model\Product $product
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
                $this->addMessageAndLog("SAVED QTY: sku: '{$sku}' - product id <{$productId}> : " . json_encode($stockUpdateData), "success");
            } else {
                $this->addMessageAndLog("SKIP QTY: sku '{$sku}' - product id <{$productId}> no data was changed", "success");
            }
        } catch (\Exception $e) {
            $this->addMessageAndLog("ERROR QTY: sku '{$sku}' - product id <{$productId} " . $e->getMessage(), "error");
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
