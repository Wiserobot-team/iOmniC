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

use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory as ShipmentCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Webapi\Exception as WebapiException;

class ShipmentIo implements \WiseRobot\Io\Api\ShipmentIoInterface
{
    /**
     * @var array
     */
    public array $results = [];
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;
    /**
     * @var ShipmentFactory
     */
    public $shipmentFactory;
    /**
     * @var ShipmentCollectionFactory
     */
    public $shipmentCollectionFactory;
    /**
     * @var ResourceConnection
     */
    public $resourceConnection;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ShipmentFactory $shipmentFactory
     * @param ShipmentCollectionFactory $shipmentCollectionFactory
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ShipmentFactory $shipmentFactory,
        ShipmentCollectionFactory $shipmentCollectionFactory,
        ResourceConnection $resourceConnection
    ) {
        $this->storeManager = $storeManager;
        $this->shipmentFactory = $shipmentFactory;
        $this->shipmentCollectionFactory = $shipmentCollectionFactory;
        $this->resourceConnection = $resourceConnection;
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
        // create shipment collection
        $shipmentCollection = $this->shipmentCollectionFactory->create();
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
        $shipmentCollection->addFieldToFilter('store_id', $store);

        // selecting
        $shipmentCollection->addFieldToSelect('*');

        // filtering
        $filter = trim((string) $filter);
        if ($filter) {
            $filterArray = explode(" and ", (string) $filter);
            foreach ($filterArray as $filterItem) {
                $operator = $this->processFilter((string) $filterItem);
                if (!$operator) {
                    continue;
                }
                $condition = array_map('trim', explode($operator, (string) $filterItem));
                if (count($condition) != 2) {
                    continue;
                }
                if (!$condition[0] || !$condition[1]) {
                    continue;
                }
                $fieldName = $condition[0];
                $fieldValue = $condition[1];

                // check if column doesn't exist in shipment table
                $tableName = $this->resourceConnection->getTableName(['sales_shipment', '']);
                if ($this->resourceConnection->getConnection()
                        ->tableColumnExists($tableName, $fieldName) !== true) {
                    $message = "Field: 'filter' - column '" .
                        $fieldName . "' doesn't exist in shipment table";
                    $this->results["error"] = $message;
                    throw new WebapiException(__($errorMess), 0, 400, $this->results);
                }
                $shipmentCollection->addFieldToFilter(
                    $fieldName,
                    [$operator => $fieldValue]
                );
            }
        }
        // sorting
        $shipmentCollection->setOrder('entity_id', 'asc');

        // paging
        $total = $shipmentCollection->getSize();
        if (!$page || $page <= 0) {
            $page = 1;
        }
        if (!$limit || $limit <= 0) {
            $limit = 100;
        }
        if ($limit > 1000) {
            $limit = 1000; // maximum page size
        }

        $result = [];
        $totalPages = ceil($total / $limit);
        if ($page > $totalPages) {
            return $result;
        }

        $shipmentCollection->setPageSize($limit);
        $shipmentCollection->setCurPage($page);
        if ($shipmentCollection->getSize()) {
            foreach ($shipmentCollection as $shipment) {
                $shipmentIId = $shipment->getIncrementId();
                if (!$shipmentIId) {
                    continue;
                }
                // shipment info
                $shipmentData = [];
                $shipmentData['store'] = $storeInfo->getName();
                $shipmentInfo = $this->getShipmentInfo($shipment);
                if (count($shipmentInfo)) {
                    $shipmentData['shipment_info'] = $shipmentInfo;
                }
                $result[$shipmentIId] = $shipmentData;
            }
            return $result;
        }

        return $result;
    }

    /**
     * Process filter data
     *
     * @param string $string
     * @return string
     */
    public function processFilter(string $string): string
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
            default:
                $operator = '';
        }

        return $operator;
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
        // shipment item
        $itemsData = [];
        $shipmentItems = $shipment->getItemsCollection();
        foreach ($shipmentItems as $shipmentItem) {
            $itemsData[] = [
                "sku" => $shipmentItem->getData("sku"),
                "name" => $shipmentItem->getData("name"),
                "price" => $shipmentItem->getData("price"),
                "qty" => $shipmentItem->getData("qty"),
                "weight" => $shipmentItem->getData("weight")
            ];
        }
        // track info
        $tracksData = [];
        $shipmentTracks = $shipment->getTracksCollection();
        foreach ($shipmentTracks as $shipmentTrack) {
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
}
