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
use Magento\Framework\Webapi\Exception as WebapiException;

class ShipmentManagement implements \WiseRobot\Io\Api\ShipmentManagementInterface
{
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
     * @param Filesystem $filesystem
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param OrderFactory $orderFactory
     * @param ConvertOrder $convertOrder
     * @param ShipmentFactory $shipmentFactory
     * @param ShipmentCollectionFactory $shipmentCollectionFactory
     * @param ShipmentTrackFactory $shipmentTrackFactory
     * @param ShipmentRepositoryInterface $shipmentRepository
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
        ShipmentRepositoryInterface $shipmentRepository
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
                    $result[$shipmentIId] = array_merge(['store' => $storeName], $shipmentData);
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
     * Get store information
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
            throw new WebapiException(__("data request error"), 0, 400, $this->results);
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
     * Apply filters to the shipment collection
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
            if (count($condition) != 2 || !$condition[0] || !$condition[1]) {
                continue;
            }
            $fieldName = $condition[0];
            $fieldValue = $condition[1];
            if (!in_array($fieldName, $columnNames)) {
                $message = "Field: 'filter' - column '{$fieldName}' doesn't exist in shipment table";
                $this->results["error"] = $message;
                throw new WebapiException(__("data request error"), 0, 400, $this->results);
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
            ' eq ' => 'eq',
            ' gt ' => 'gt',
            ' le ' => 'le',
        ];
        foreach ($operators as $key => $operator) {
            if (strpos($string, $key) !== false) {
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
     * @param string $orderId
     * @param mixed $shipmentInfo
     * @return array
     */
    public function import(string $orderId, mixed $shipmentInfo): array
    {
        $this->initializeResults();
        $this->validateShipmentInfo($orderId, $shipmentInfo);
        try {
            $this->importIoShipment($orderId, $shipmentInfo);
            $this->cleanResponseMessages();
            return $this->results;
        } catch (\Exception $e) {
            $errorMess = "shipment import error";
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }
    }

    /**
     * Validate Shipment Info
     *
     * @param string $orderId
     * @param mixed $shipmentInfo
     * @return void
     */
    public function validateShipmentInfo(string $orderId, mixed $shipmentInfo): void
    {
        if (empty($orderId)) {
            $this->addError("Field: 'order_id' is a required field");
        }
        if (empty($shipmentInfo) || !is_array($shipmentInfo)) {
            $this->addError("Field: 'shipment_info' is a required field");
        }
        foreach ($shipmentInfo as $_shipment) {
            if (empty($_shipment["shipping_date"]) || empty($_shipment["item_info"])
                || !is_array($_shipment["item_info"]) || empty($_shipment["track_info"])
                || !is_array($_shipment["track_info"])) {
                $this->addError("Field: 'shipment_info' - incorrect data");
            }
            foreach ($_shipment["item_info"] as $item) {
                if (empty($item["sku"]) || empty($item["qty"])) {
                    $this->addError("Field: 'item_info' - {'sku','qty'} data fields are required");
                }
            }
            foreach ($_shipment["track_info"] as $track) {
                if (empty($track["carrier_code"]) || empty($track["title"])) {
                    $this->addError("Field: 'track_info' - {'carrier_code','title'} data fields are required");
                }
            }
        }
    }

    /**
     * Import Io Shipment
     *
     * @param string $orderId
     * @param array $shipmentInfo
     * @return bool
     */
    public function importIoShipment(string $orderId, array $shipmentInfo): bool
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (!$order || !$order->getId()) {
            $message = "WARN cannot load order {$orderId}";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log($message);
            return false;
        }
        if ($order->getStatus() == "closed") {
            $message = "Skip order {$orderId} has been closed";
            $this->results["response"]["data"]["success"][] = $message;
            $this->log($message);
            return false;
        }
        if ($order->hasShipments()) {
            $message = "Skip order {$orderId} has been shipped";
            $this->results["response"]["data"]["success"][] = $message;
            $this->log($message);
            return false;
        }
        try {
            $this->createShipment($order, $shipmentInfo);
            return true;
        } catch (\Exception $e) {
            $message = "{$orderId}: {$e->getMessage()}";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log("ERROR {$message}");
            $this->cleanResponseMessages();
            throw new WebapiException(__($e->getMessage()), 0, 400);
        }
        return false;
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
        foreach ($shipmentInfo as $_shipmentInfo) {
            try {
                $shipment = $this->convertOrder->toShipment($order);
                $shipment->setCreatedAt($_shipmentInfo["shipping_date"]);
                $this->addShipmentItems($order, $shipment, $_shipmentInfo["item_info"]);
                $this->addShipmentTrack($shipment, $_shipmentInfo["track_info"][0]);
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                $shipment->save();
                $shipment->getOrder()->save();
                $shipmentId = $shipment->getIncrementId();
                $message = "Shipment '{$shipmentId}' imported for order {$order->getIncrementId()}";
                $this->results["response"]["data"]["success"][] = $message;
                $this->log($message);
            } catch (\Exception $e) {
                throw new WebapiException(__("create shipment " . $e->getMessage()), 0, 400);
            }
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
    private function initializeResults(): void
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
     * Add error message
     *
     * @param string $message
     * @return void
     */
    public function addError(string $message): void
    {
        $errorMess = "data request error";
        $this->results["response"]["data"]["error"][] = $message;
        $this->log("ERROR {$message}");
        $this->cleanResponseMessages();
        throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
    }

    /**
     * Clean response Message
     *
     * @return void
     */
    public function cleanResponseMessages(): void
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
    public function log(string $message): void
    {
        $logDir = $this->filesystem->getDirectoryWrite(DirectoryList::LOG);
        $writer = new \Zend_Log_Writer_Stream($logDir->getAbsolutePath('') . $this->logFile);
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info($message);
    }
}
