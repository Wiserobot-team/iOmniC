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
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Convert\Order as ConvertOrder;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory as ShipmentCollectionFactory;
use Magento\Sales\Model\Order\Shipment\TrackFactory as ShipmentTrackFactory;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\History\Collection as HistoryCollection;
use Magento\Shipping\Model\ShipmentNotifier;
use Magento\Framework\Webapi\Exception as WebapiException;

class ShipmentManagement implements \WiseRobot\Io\Api\ShipmentManagementInterface
{
    /**
     * @var Zend_Log
     */
    public $logger;
    /**
     * @var string
     */
    public $logFile = "wr_io_shipment_import.log";
    /**
     * @var array
     */
    public array $results = [];
    /**
     * @var Filesystem
     */
    public $filesystem;
    /**
     * @var ResourceConnection
     */
    public $resourceConnection;
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;
    /**
     * @var OrderFactory
     */
    public $orderFactory;
    /**
     * @var ConvertOrder
     */
    public $convertOrder;
    /**
     * @var ShipmentFactory
     */
    public $shipmentFactory;
    /**
     * @var ShipmentCollectionFactory
     */
    public $shipmentCollectionFactory;
    /**
     * @var ShipmentTrackFactory
     */
    public $shipmentTrackFactory;
    /**
     * @var ShipmentRepositoryInterface
     */
    public $shipmentRepository;
    /**
     * @var HistoryCollection
     */
    public $historyCollection;
    /**
     * @var ShipmentNotifier
     */
    public $shipmentNotifier;

    /**
     * @param Filesystem $filesystem
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param OrderFactory $orderFactory
     * @param ConvertOrder $convertOrder
     * @param ShipmentFactory $shipmentFactory
     * @param ShipmentCollectionFactory $shipmentCollectionFactory
     * @param ShipmentTrackFactory $shipmentTrackFactory
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param HistoryCollection $historyCollection
     * @param ShipmentNotifier $shipmentNotifier
     */
    public function __construct(
        Filesystem $filesystem,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        OrderFactory $orderFactory,
        ConvertOrder $convertOrder,
        ShipmentFactory $shipmentFactory,
        ShipmentCollectionFactory $shipmentCollectionFactory,
        ShipmentTrackFactory $shipmentTrackFactory,
        ShipmentRepositoryInterface $shipmentRepository,
        HistoryCollection $historyCollection,
        ShipmentNotifier $shipmentNotifier
    ) {
        $this->filesystem = $filesystem;
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->orderFactory = $orderFactory;
        $this->convertOrder = $convertOrder;
        $this->shipmentFactory = $shipmentFactory;
        $this->shipmentCollectionFactory = $shipmentCollectionFactory;
        $this->shipmentTrackFactory = $shipmentTrackFactory;
        $this->shipmentRepository = $shipmentRepository;
        $this->historyCollection = $historyCollection;
        $this->shipmentNotifier = $shipmentNotifier;
        $this->initializeResults();
        $this->initializeLogger();
    }

    /**
     * Filter Shipment Data
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
        int $limit = 1000
    ): array {
        $storeInfo = $this->getStoreInfo($store);
        $shipmentCollection = $this->createShipmentCollection($store);
        $this->applyFilter($shipmentCollection, $filter);
        $this->applySortingAndPaging($shipmentCollection, $page, $limit);
        $result = [];
        $storeName = $storeInfo->getName();
        foreach ($shipmentCollection as $shipment) {
            $shipmentIId = $shipment->getIncrementId();
            if ($shipmentIId) {
                $shipmentData = $this->formatShipmentData($shipment);
                if (!empty($shipmentData)) {
                    $shipmentData['store'] = $storeName;
                    $result[$shipmentIId] = $shipmentData;
                }
            }
        }
        return $result;
    }

    /**
     * Get Shipment by ID
     *
     * @param int $shipmentId
     * @return array
     */
    public function getById(int $shipmentId): array
    {
        try {
            $shipment = $this->shipmentRepository->get($shipmentId);
            $shipmentData = $this->formatShipmentData($shipment);
            return !empty($shipmentData) ? [$shipmentId => $shipmentData] : [];
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return [];
        }
    }

    /**
     * Get Shipment by increment ID
     *
     * @param string $incrementId
     * @return array
     */
    public function getByIncrementId(string $incrementId): array
    {
        $shipmentCollection = $this->shipmentCollectionFactory->create()
            ->addFieldToFilter('increment_id', $incrementId)
            ->setPageSize(1);
        $shipment = $shipmentCollection->getFirstItem();
        if (!$shipment->getId()) {
            return [];
        }
        $shipmentData = $this->formatShipmentData($shipment);
        return !empty($shipmentData) ? [$incrementId => $shipmentData] : [];
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
     * Create shipment collection with basic filters
     *
     * @param int $store
     * @return \Magento\Sales\Model\ResourceModel\Order\Shipment\Collection
     */
    public function createShipmentCollection(
        int $store
    ): \Magento\Sales\Model\ResourceModel\Order\Shipment\Collection {
        $shipmentCollection = $this->shipmentCollectionFactory->create();
        $shipmentCollection->addFieldToFilter('main_table.store_id', $store)
            ->addFieldToSelect('*')
            ->getSelect()
            ->distinct(true)
            ->joinLeft(
                ['shipment_track' => $this->resourceConnection->getTableName('sales_shipment_track')],
                'main_table.entity_id = shipment_track.parent_id',
                ['shipment_track_updated_at' => 'shipment_track.updated_at']
            )
            ->group('main_table.entity_id');
        return $shipmentCollection;
    }

    /**
     * Apply sorting and paging to the shipment collection
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\Shipment\Collection $shipmentCollection
     * @param int $page
     * @param int $limit
     */
    public function applySortingAndPaging(
        \Magento\Sales\Model\ResourceModel\Order\Shipment\Collection $shipmentCollection,
        int $page,
        int $limit
    ): void {
        $shipmentCollection->setOrder('entity_id', 'asc')
            ->setPageSize(min(max(1, (int) $limit), 1000))
            ->setCurPage(max(1, (int) $page));
    }

    /**
     * Apply filter to the shipment collection
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\Shipment\Collection $shipmentCollection
     * @param string $filter
     */
    public function applyFilter(
        \Magento\Sales\Model\ResourceModel\Order\Shipment\Collection $shipmentCollection,
        string $filter
    ): void {
        $filter = trim((string) $filter);
        $filterArray = explode(" and ", (string) $filter);
        $tableName = $this->resourceConnection->getTableName('sales_shipment');
        $columns = $this->resourceConnection->getConnection()->describeTable($tableName);
        $columnNames = array_keys($columns);
        foreach ($filterArray as $filterItem) {
            $operator = $this->processFilter($filterItem);
            if (!$operator) {
                continue;
            }
            $condition = array_map('trim', explode($operator, (string) $filterItem));
            if (count($condition) !== 2 || empty($condition[0]) || empty($condition[1])) {
                continue;
            }
            $fieldName = $condition[0];
            $fieldValue = $condition[1];
            if (!in_array($fieldName, $columnNames)) {
                $message = "Field: 'filter' - column '{$fieldName}' doesn't exist in shipment table";
                $this->results["error"] = $message;
                throw new WebapiException(__($message), 0, 400, $this->results);
            }
            $operator = trim($operator);
            if (in_array($operator, ['in', 'nin'])) {
                $fieldValue = array_map('trim', explode(",", $fieldValue));
            }
            if ($fieldName === "updated_at") {
                $shipmentCollection->addFieldToFilter(
                    ['main_table.updated_at', 'shipment_track.updated_at'],
                    [
                        [$operator => $fieldValue],
                        [$operator => $fieldValue]
                    ]
                );
            } else {
                $shipmentCollection->addFieldToFilter(
                    'main_table.' . $fieldName,
                    [$operator => $fieldValue]
                );
            }
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
     * Get Shipment Data
     *
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return array
     */
    public function formatShipmentData(
        \Magento\Sales\Model\Order\Shipment $shipment
    ): array {
        $shipmentInfo = $this->getShipmentInfo($shipment);
        return !empty($shipmentInfo) ? ['shipment_info' => $shipmentInfo] : [];
    }

    /**
     * Get Order Shipment Info
     *
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return array
     */
    public function getShipmentInfo(
        \Magento\Sales\Model\Order\Shipment $shipment
    ): array {
        $shipmentInfo = [];
        $itemsData = [];
        foreach ($shipment->getItemsCollection() as $shipmentItem) {
            $itemsData[] = [
                "sku" => $shipmentItem->getData("sku"),
                "name" => $shipmentItem->getData("name"),
                "price" => $shipmentItem->getData("price"),
                "qty" => $shipmentItem->getData("qty"),
                "weight" => $shipmentItem->getData("weight")
            ];
        }
        $tracksData = [];
        foreach ($shipment->getTracksCollection() as $shipmentTrack) {
            $tracksData[] = [
                "created_at" => $shipmentTrack->getData("created_at"),
                "updated_at" => $shipmentTrack->getData("updated_at"),
                "carrier_code" => $shipmentTrack->getData("carrier_code"),
                "title" => $shipmentTrack->getData("title"),
                "track_number" => $shipmentTrack->getData("track_number")
            ];
        }
        $shipmentInfo[] = [
            "created_at" => $shipment->getData("created_at"),
            "updated_at" => $shipment->getData("updated_at"),
            "store_id" => $shipment->getData("store_id"),
            "entity_id" => $shipment->getData("entity_id"),
            "increment_id" => $shipment->getData("increment_id"),
            "order_id" => $shipment->getData("order_id"),
            "total_qty" => $shipment->getData("total_qty"),
            "total_weight" => $shipment->getData("total_weight"),
            "item_info" => $itemsData,
            "track_info" => $tracksData
        ];
        return $shipmentInfo;
    }

    /**
     * Import Shipment
     *
     * @param string $incrementId
     * @param mixed $shipmentInfo
     * @return array
     */
    public function import(string $incrementId, mixed $shipmentInfo): array
    {
        $this->validateShipmentInfo($incrementId, $shipmentInfo);
        $this->importIoShipment($incrementId, $shipmentInfo);
        $this->cleanResponseMessages();
        return $this->results;
    }

    /**
     * Validate Shipment Info
     *
     * @param string $incrementId
     * @param mixed $shipmentInfo
     * @return void
     */
    public function validateShipmentInfo(string $incrementId, mixed $shipmentInfo): void
    {
        if (empty($incrementId)) {
            $this->addMessageAndLog("Field: 'order_id' is a required field", "error");
        }
        if (empty($shipmentInfo) || !is_array($shipmentInfo)) {
            $this->addMessageAndLog("Field: 'shipment_info' is a required field", "error");
        }
        foreach ($shipmentInfo as $shipment) {
            if (empty($shipment["shipping_date"]) || empty($shipment["item_info"])
                || !is_array($shipment["item_info"]) || empty($shipment["track_info"])
                || !is_array($shipment["track_info"])) {
                $this->addMessageAndLog("Field: 'shipment_info' - incorrect data", "error");
            }
            foreach ($shipment["item_info"] as $item) {
                if (empty($item["sku"]) || empty($item["qty"])) {
                    $this->addMessageAndLog("Field: 'item_info' - {'sku','qty'} data fields are required", "error");
                }
            }
            foreach ($shipment["track_info"] as $track) {
                if (empty($track["carrier_code"]) || empty($track["title"])) {
                    $message = "Field: 'track_info' - {'carrier_code','title'} data fields are required";
                    $this->addMessageAndLog($message, "error");
                }
            }
        }
    }

    /**
     * Import Io Shipment
     *
     * @param string $incrementId
     * @param array $shipmentInfo
     * @return bool
     */
    public function importIoShipment(string $incrementId, array $shipmentInfo): bool
    {
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
        if (!$order || !$order->getId()) {
            $this->addMessageAndLog("Cannot load order {$incrementId}", "error");
        }
        if ($order->getStatus() == "closed") {
            $this->addMessageAndLog("Order {$incrementId} has been closed", "success");
            return true;
        }
        if ($order->hasShipments()) {
            $this->addMessageAndLog("Order {$incrementId} has been shipped", "success");
            return true;
        }
        $this->createShipment($order, $shipmentInfo);
        return true;
    }

    /**
     * Create Shipment
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $shipmentInfo
     * @return void
     */
    public function createShipment(
        \Magento\Sales\Model\Order $order,
        array $shipmentInfo
    ): void {
        try {
            foreach ($shipmentInfo as $_shipmentInfo) {
                $shipment = $this->convertOrder->toShipment($order);
                $shipment->setCreatedAt($_shipmentInfo["shipping_date"]);
                $this->addShipmentItems($order, $shipment, $_shipmentInfo["item_info"]);
                $this->addShipmentTrack($shipment, $_shipmentInfo["track_info"][0]);
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                $shipment->save();
                $shipment->getOrder()->save();
                if (!empty($_shipmentInfo['send_email'])) {
                    $this->shipmentNotifier->notify($shipment);
                    $shipment->save();
                    $historyItem = $this->historyCollection->getUnnotifiedForInstance($shipment);
                    if ($historyItem) {
                        $historyItem->setIsCustomerNotified(1);
                        $historyItem->save();
                    }
                }
                $message = "Shipment '{$shipment->getIncrementId()}' imported for order {$order->getIncrementId()}";
                $this->addMessageAndLog($message, "success");
            }
        } catch (\Exception $e) {
            $this->addMessageAndLog("ERROR create shipment {$e->getMessage()}", "error");
        }
    }

    /**
     * Add Shipment Items
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param array $itemInfo
     * @return void
     */
    public function addShipmentItems(
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\Order\Shipment $shipment,
        array $itemInfo
    ): void {
        foreach ($order->getAllItems() as $orderItem) {
            if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }
            foreach ($itemInfo as $item) {
                if ($item["sku"] == $orderItem->getSku()) {
                    $qtyShipped = (int) $item["qty"];
                    $shipmentItem = $this->convertOrder->itemToShipmentItem($orderItem)
                        ->setQty($qtyShipped);
                    $shipment->addItem($shipmentItem);
                }
            }
        }
    }

    /**
     * Add Shipment Track
     *
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param array $trackInfo
     * @return void
     */
    public function addShipmentTrack(
        \Magento\Sales\Model\Order\Shipment $shipment,
        array $trackInfo
    ): void {
        $trackingDetail = [
            "carrier_code" => $trackInfo["carrier_code"],
            "title" => $trackInfo["title"],
            "number" => $trackInfo["track_number"] ?? "N/A",
            "created_at" => $shipment->getCreatedAt()
        ];
        $shipmentTrack = $this->shipmentTrackFactory->create()->addData($trackingDetail);
        $shipment->addTrack($shipmentTrack);
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
