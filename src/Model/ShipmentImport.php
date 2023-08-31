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

use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Convert\Order as ConvertOrder;
use Magento\Sales\Model\Order\Shipment\TrackFactory as ShipmentTrackFactory;

class ShipmentImport implements \Wiserobot\Io\Api\ShipmentImportInterface
{
    private $logFile = "wiserobotio_shipment_import.log";
    private $showLog = false;
    public $results  = [];
    public $filesystem;
    public $orderFactory;
    public $convertOrder;
    public $shipmentTrackFactory;

    public function __construct(
        Filesystem                  $filesystem,
        OrderFactory                $orderFactory,
        ConvertOrder                $convertOrder,
        ShipmentTrackFactory        $shipmentTrackFactory
    ) {
        $this->filesystem           = $filesystem;
        $this->orderFactory         = $orderFactory;
        $this->convertOrder         = $convertOrder;
        $this->shipmentTrackFactory = $shipmentTrackFactory;
    }

    public function import($order_id, $shipment_info)
    {
        // response messages
        $this->results["response"]["data"]["success"] = [];
        $this->results["response"]["data"]["error"]   = [];

        // order id
        if (!$order_id) {
            $this->results["response"]["data"]["error"][] = "Field: 'order_id' is a required field";
            $this->log("ERROR: Field: 'order_id' is a required field");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }

        // shipment info
        if (!$shipment_info || !count($shipment_info)) {
            $this->results["response"]["data"]["error"][] = "Field: 'shipment_info' is a required field";
            $this->log("ERROR: Field: 'shipment_info' is a required field");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        foreach ($shipment_info as $shipment) {
            if (!isset($shipment["shipping_date"]) || !$shipment["shipping_date"]
                || !isset($shipment["item_info"])  || !count($shipment["item_info"])
                || !isset($shipment["track_info"]) || !count($shipment["track_info"])) {
                $this->results["response"]["data"]["error"][] = "Field: 'shipment_info' - incorrect data";
                $this->log("ERROR: Field: 'shipment_info' - incorrect data");
                $this->cleanResponseMessages();
                throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
            }
            foreach ($shipment["item_info"] as $item) {
                if (!isset($item["sku"]) || !$item["sku"] || !isset($item["qty"]) || !$item["qty"]) {
                    $this->results["response"]["data"]["error"][] = "Field: 'item_info' - {'sku','qty'} data fields are required";
                    $this->log("ERROR: Field: 'item_info' - {'sku','qty'} data fields are required");
                    $this->cleanResponseMessages();
                    throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
                }
            }
            foreach ($shipment["track_info"] as $track) {
                if (!isset($track["carrier_code"]) || !$track["carrier_code"] || !isset($track["title"]) || !$track["title"]) {
                    $this->results["response"]["data"]["error"][] = "Field: 'track_info' - {'carrier_code','title'} data fields are required";
                    $this->log("ERROR: Field: 'track_info' - {'carrier_code','title'} data fields are required");
                    $this->cleanResponseMessages();
                    throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
                }
            }
        }

        // import io shipment
        try {
            $this->importIoShipment($order_id, $shipment_info);
            $this->cleanResponseMessages();
            return $this->results;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Webapi\Exception(__("shipment import error"), 0, 400, $this->results["response"]);
        }
    }

    public function importIoShipment($orderId, $shipmentInfo)
    {
        try {
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);
            if ($order && $order->getId()) {
                if ($order->getStatus() == "closed") {
                    $this->results["response"]["data"]["success"][] = "skip order " . $orderId . " has been closed";
                    $this->log("Skip order " . $orderId . " has been closed");
                    return;
                }
                if ($order->hasShipments()) {
                    $this->results["response"]["data"]["success"][] = "skip order " . $orderId . " has been shipped";
                    $this->log("Skip order " . $orderId . " has been shipped");
                    return;
                }
                // create shipment for order
                $this->createShipment($order, $shipmentInfo);
            } else {
                $this->results["response"]["data"]["error"][] = "warn cannot load order " . $orderId;
                $this->log("WARN cannot load order " . $orderId);
                return;
            }
        } catch (\Exception $e) {
            $this->results["response"]["data"]["error"][] = $orderId . ": " . $e->getMessage();
            $message = "ERROR " . $orderId . ": " . $e->getMessage();
            $this->log($message);
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__($e->getMessage()), 0, 400);
        }
    }

    public function createShipment($order, $shipmentInfo)
    {
        if ($order->hasShipments()) {
            $this->results["response"]["data"]["success"][] = "skip order " . $order->getIncrementId() . " for already has shipment";
            $this->log("Skip order " . $order->getIncrementId() . " for already has shipment");
            return;
        }

        // create shipment
        foreach ($shipmentInfo as $_shipmentInfo) {
            try {
                // shipping date
                if (!$_shipmentInfo["shipping_date"]) {
                    continue;
                }
                // item info
                if (!isset($_shipmentInfo["item_info"]) || !count($_shipmentInfo["item_info"]) || !isset($_shipmentInfo["item_info"][0])) {
                    continue;
                }
                $hasItem = false;
                foreach ($_shipmentInfo["item_info"] as $itemInfo) {
                    if (!$itemInfo["sku"] || !$itemInfo["qty"]) {
                        continue;
                    }
                    $hasItem = true;
                }
                if (!$hasItem) {
                    continue;
                }
                // track info
                if (!isset($_shipmentInfo["track_info"]) || !count($_shipmentInfo["track_info"]) || !isset($_shipmentInfo["track_info"][0])) {
                    continue;
                }
                $trackInfo = $_shipmentInfo["track_info"][0];
                if (!$trackInfo["carrier_code"] || !$trackInfo["title"]) {
                    continue;
                }
                if (!isset($trackInfo["track_number"])) {
                    $trackingNumber = "N/A";
                } else {
                    $trackingNumber = $trackInfo["track_number"];
                    if (!$trackingNumber) {
                        $trackingNumber = "N/A";
                    }
                }

                $carrierCode  = $trackInfo["carrier_code"];
                $className    = $trackInfo["title"];
                $shippingDate = $_shipmentInfo["shipping_date"];

                $trackingDetail = array(
                    "carrier_code" => $carrierCode,
                    "title" => $className,
                    "number" => $trackingNumber,
                    "created_at" => $shippingDate
                );

                $convertOrder = $this->convertOrder;
                $shipment     = $convertOrder->toShipment($order);
                $shipment->setCreatedAt($shippingDate);

                foreach ($order->getAllItems() as $orderItem) {
                    if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                        continue;
                    }
                    foreach ($_shipmentInfo["item_info"] as $itemInfo) {
                        if ($itemInfo["sku"] == $orderItem->getSku()) {
                            $qtyShipped   = (int) $itemInfo["qty"];
                            $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);
                            $shipment->addItem($shipmentItem);
                        }
                    }
                }

                $track = $this->shipmentTrackFactory->create()->addData($trackingDetail);
                $shipment->addTrack($track);
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                $shipment->save();
                $shipment->getOrder()->save();
                $this->results["response"]["data"]["success"][] = "shipment '" . $shipment->getIncrementId() . "' imported for order " . $order->getIncrementId();
                $this->log("Shipment '" . $shipment->getIncrementId() . "' imported for order " . $order->getIncrementId());
            } catch (\Exception $e) {
                throw new \Magento\Framework\Webapi\Exception(__("create shipment " . $e->getMessage()), 0, 400);
            }
        }
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
