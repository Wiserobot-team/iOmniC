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

use Zend\Log\Writer\Stream;
use Zend\Log\Logger;

use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order\CreditmemoFactory;

class RefundImport implements \Wiserobot\Io\Api\RefundImportInterface
{
    public $logFile = "wiserobotio_refund_import.log";
    public $showLog = false;
    public $results = [];

    public function __construct(
        Filesystem               $filesystem,
        OrderFactory             $orderFactory,
        CreditmemoFactory        $creditmemoFactory
    ) {
        $this->filesystem        = $filesystem;
        $this->orderFactory      = $orderFactory;
        $this->creditmemoFactory = $creditmemoFactory;

        register_shutdown_function([$this, 'shutdownHandler']);
    }

    public function shutdownHandler()
    {
        $error = error_get_last();
        if (is_null($error)) {
            return;
        } else {
            $this->log(print_r($error));
        }
    }

    public function import($order_id, $refund_info)
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

        // refund info
        if (!$refund_info || !count($refund_info)) {
            $this->results["response"]["data"]["error"][] = "Field: 'refund_info' is a required field";
            $this->log("ERROR: Field: 'refund_info' is a required field");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        foreach ($refund_info as $refund) {
            if (!isset($refund["refund_date"])      || !$refund["refund_date"]
                || !isset($refund["refund_status"]) || !$refund["refund_status"]
                || !isset($refund["item_info"])     || !count($refund["item_info"])) {
                $this->results["response"]["data"]["error"][] = "Field: 'refund_info' - incorrect data";
                $this->log("ERROR: Field: 'refund_info' - incorrect data");
                $this->cleanResponseMessages();
                throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
            }
            foreach ($refund["item_info"] as $item) {
                if (!isset($item["sku"])    || !$item["sku"]
                    || !isset($item["qty"]) || !$item["qty"]) {
                    $this->results["response"]["data"]["error"][] = "Field: 'item_info' - {'sku','qty'} data fields are required";
                    $this->log("ERROR: Field: 'item_info' - {'sku','qty'} data fields are required");
                    $this->cleanResponseMessages();
                    throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
                }
            }
        }

        // import io refund
        try {
            $this->importIoRefund($order_id, $refund_info);
            $this->cleanResponseMessages();
            return $this->results;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Webapi\Exception(__("refund import error"), 0, 400, $this->results["response"]);
        }
    }

    public function importIoRefund($orderId, $refundInfo)
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
                    // create credit memo for order
                    if ($order->hasInvoices()) {
                        if (!$order->hasCreditmemos()) {
                            $this->createCreditMemo($order, $refundInfo);
                        } else {
                            $this->results["response"]["data"]["success"][] = "skip order " . $order->getIncrementId() . " has been refunded";
                            $this->log("Skip order " . $order->getIncrementId() . " has been refunded");
                            return;
                        }
                    } else {
                        $order->setData("status", "closed");
                        $order->setData("state", "closed");
                        $order->save();
                        $this->results["response"]["data"]["success"][] = "order " . $order->getIncrementId() . " set closed status success";
                        $this->log("Order " . $order->getIncrementId() . " set closed status success");
                        return;
                    }
                } else {
                    // check and create credit memo for order
                    if ($order->hasInvoices()) {
                        if (!$order->hasCreditmemos()) {
                            $this->createCreditMemo($order, $refundInfo);
                            // update order status
                            $orderObject = $this->orderFactory->create()->loadByIncrementId($order->getIncrementId());
                            if ($orderObject->getStatus() != "closed") {
                                if (in_array($orderObject->getStatus(), ["processing"])) {
                                    $orderObject->setData("status", "closed");
                                    $orderObject->setData("state", "closed");
                                    $orderObject->save();
                                    return;
                                }
                            }
                        } else {
                            $this->results["response"]["data"]["success"][] = "skip order " . $order->getIncrementId() . " has been refunded";
                            $this->log("Skip order " . $order->getIncrementId() . " has been refunded");
                            return;
                        }
                    } else {
                        $order->setData("status", "closed");
                        $order->setData("state", "closed");
                        $order->save();
                        $this->results["response"]["data"]["success"][] = "order " . $order->getIncrementId() . " set closed status success";
                        $this->log("Order " . $order->getIncrementId() . " set closed status success");
                        return;
                    }
                }
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

    public function createCreditMemo($order, $refundInfo)
    {
        $shippingRefundedTotal    = 0;
        $shippingTaxRefundedTotal = 0;
        $taxRefundedTotal         = 0;
        $subtotalRefundedTotal    = 0;
        $totalRefundedTotal       = 0;
        foreach ($refundInfo as $_refundInfo) {
            // item info
            if (!isset($_refundInfo["item_info"]) || !count($_refundInfo["item_info"]) || !isset($_refundInfo["item_info"][0])) {
                continue;
            }
            $hasItem = false;
            foreach ($_refundInfo["item_info"] as $itemInfo) {
                if (!$itemInfo["sku"] || !$itemInfo["qty"]) {
                    continue;
                }
                $hasItem = true;
            }
            if (!$hasItem) {
                continue;
            }
            $infoItems = [];
            foreach ($_refundInfo["item_info"] as $itemInfo) {
                $sku       = $itemInfo["sku"];
                $qtyRefund = (int) $itemInfo["qty"];
                foreach ($order->getAllItems() as $orderItem) {
                    if ($orderItem->getSku() == $sku) {
                        $infoItems[$orderItem->getId()] = $qtyRefund;
                    }
                }
            }
            if (!count($infoItems)) {
                $this->results["response"]["data"]["error"][] = "WARN create credit memo for order " . $order->getIncrementId() . " items ordered do not match";
                $this->log("WARN create credit memo for order " . $order->getIncrementId() . " items ordered do not match");
                return;
            }
            $creditMemoData = [
                'qtys' => $infoItems
            ];
            try {
                $creditMemo = $this->creditmemoFactory->createByOrder($order, $creditMemoData);
                $creditMemo->save();
                $shippingRefundedTotal    += (float) $creditMemo->getData("base_shipping_amount");
                $shippingTaxRefundedTotal += (float) $creditMemo->getData("shipping_tax_amount");
                $taxRefundedTotal         += (float) $creditMemo->getData("tax_amount");
                $subtotalRefundedTotal    += (float) $creditMemo->getData("subtotal");
                $totalRefundedTotal       += (float) $creditMemo->getData("base_grand_total");

                $this->results["response"]["data"]["success"][] = "credit memo '" . $creditMemo->getIncrementId() . "' imported for order " . $order->getIncrementId();
                $this->log("Credit Memo '" . $creditMemo->getIncrementId() . "' imported for order " . $order->getIncrementId());
            } catch (\Exception $e) {
                $this->results["response"]["data"]["error"][] = "create credit memo " . $e->getMessage();
                $this->log("ERROR create credit memo " . $e->getMessage());
                throw new \Magento\Framework\Webapi\Exception(__("create credit memo " . $e->getMessage()), 0, 400);
            }
        }
        // reset order after save credit memo
        $order->setData("shipping_refunded", $shippingRefundedTotal);
        $order->setData("base_shipping_refunded", $shippingRefundedTotal);
        $order->setData("shipping_tax_refunded", $shippingTaxRefundedTotal);
        $order->setData("tax_refunded", $taxRefundedTotal);
        $order->setData("base_tax_refunded", $taxRefundedTotal);
        $order->setData("subtotal_refunded", $subtotalRefundedTotal);
        $order->setData("base_subtotal_refunded", $subtotalRefundedTotal);
        $order->setData("total_refunded", $totalRefundedTotal);
        $order->setData("base_total_refunded", $totalRefundedTotal);
        $order->save();
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
        $writer = new Stream($logDir->getAbsolutePath('') . $this->logFile);
        $logger = new Logger();
        $logger->addWriter($writer);
        $logger->info(print_r($message, true));

        if ($this->showLog) {
            print_r($message);
            echo "\n";
        }
    }
}
