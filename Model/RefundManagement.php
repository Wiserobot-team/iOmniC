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
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Framework\Webapi\Exception as WebapiException;

class RefundManagement implements \WiseRobot\Io\Api\RefundManagementInterface
{
    /**
     * @var string
     */
    public $logFile = "wr_io_refund_import.log";
    /**
     * @var array
     */
    public array $results = [];
    /**
     * @var Filesystem
     */
    public $filesystem;
    /**
     * @var OrderFactory
     */
    public $orderFactory;
    /**
     * @var CreditmemoFactory
     */
    public $creditMemoFactory;

    /**
     * @param Filesystem $filesystem
     * @param OrderFactory $orderFactory
     * @param CreditmemoFactory $creditMemoFactory
     */
    public function __construct(
        Filesystem $filesystem,
        OrderFactory $orderFactory,
        CreditmemoFactory $creditMemoFactory
    ) {
        $this->filesystem = $filesystem;
        $this->orderFactory = $orderFactory;
        $this->creditMemoFactory = $creditMemoFactory;
    }

    /**
     * Import Order
     *
     * @param string $orderId
     * @param mixed $refundInfo
     * @return array
     */
    public function import(string $orderId, mixed $refundInfo): array
    {
        // response messages
        $this->results["response"]["data"]["success"] = [];
        $this->results["response"]["data"]["error"] = [];

        $errorMess = "data request error";

        // order id
        if (!$orderId) {
            $message = "Field: 'order_id' is a required field";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log("ERROR: " . $message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }

        // refund info
        if (!$refundInfo || !count($refundInfo)) {
            $message = "Field: 'refund_info' is a required field";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log("ERROR: " . $message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }
        foreach ($refundInfo as $_refund) {
            if (!isset($_refund["refund_date"]) || !$_refund["refund_date"] ||
                !isset($_refund["refund_status"]) || !$_refund["refund_status"] ||
                !isset($_refund["item_info"]) || !count($_refund["item_info"])) {
                $message = "Field: 'refund_info' - incorrect data";
                $this->results["response"]["data"]["error"][] = $message;
                $this->log("ERROR: " . $message);
                $this->cleanResponseMessages();
                throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
            }
            foreach ($_refund["item_info"] as $item) {
                if (!isset($item["sku"]) || !$item["sku"] ||
                    !isset($item["qty"]) || !$item["qty"]) {
                    $message = "Field: 'item_info' - {'sku','qty'} data fields are required";
                    $this->results["response"]["data"]["error"][] = $message;
                    $this->log("ERROR: " . $message);
                    $this->cleanResponseMessages();
                    throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
                }
            }
        }

        // import io refund
        try {
            $this->importIoRefund($orderId, $refundInfo);
            $this->cleanResponseMessages();
            return $this->results;
        } catch (\Exception $e) {
            $errorMess = "refund import error";
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }
    }

    /**
     * Import Refund
     *
     * @param string $orderId
     * @param array $refundInfo
     * @return bool
     */
    public function importIoRefund(string $orderId, array $refundInfo): bool
    {
        try {
            $order = $this->orderFactory->create()
                ->loadByIncrementId($orderId);
            if (!$order || !$order->getId()) {
                $message = "WARN cannot load order " . $orderId;
                $this->results["response"]["data"]["error"][] = $message;
                $this->log($message);
                return false;
            }
            if ($order->getStatus() == "closed") {
                $message = "Skip order " . $orderId . " has been closed";
                $this->results["response"]["data"]["success"][] = $message;
                $this->log($message);
                return false;
            }
            if ($order->hasShipments()) {
                // create credit memo for order
                if ($order->hasInvoices()) {
                    if (!$order->hasCreditmemos()) {
                        $this->createCreditMemo($order, $refundInfo);
                        $orderObject = $this->orderFactory->create()
                            ->loadByIncrementId($orderId);
                        if ($orderObject->getStatus() != "closed" &&
                            in_array($orderObject->getStatus(), ["complete"])) {
                            $orderObject->setData("status", "closed");
                            $orderObject->setData("state", "closed");
                            $orderObject->save();
                            return false;
                        }
                    } else {
                        $message = "Skip order " . $orderId . " has been refunded";
                        $this->results["response"]["data"]["success"][] = $message;
                        $this->log($message);
                        return false;
                    }
                } else {
                    $order->setData("status", "closed");
                    $order->setData("state", "closed");
                    $order->save();
                    $message = "Order " . $orderId . " set closed status success";
                    $this->results["response"]["data"]["success"][] = $message;
                    $this->log($message);
                    return false;
                }
            } else {
                // check and create credit memo for order
                if (!$order->hasInvoices()) {
                    $order->setData("status", "closed");
                    $order->setData("state", "closed");
                    $order->save();
                    $message = "Order " . $orderId . " set closed status success";
                    $this->results["response"]["data"]["success"][] = $message;
                    $this->log($message);
                    return false;
                }
                if (!$order->hasCreditmemos()) {
                    $this->createCreditMemo($order, $refundInfo);
                    $orderObject = $this->orderFactory->create()
                        ->loadByIncrementId($orderId);
                    if ($orderObject->getStatus() != "closed" &&
                        in_array($orderObject->getStatus(), ["processing"])) {
                        $orderObject->setData("status", "closed");
                        $orderObject->setData("state", "closed");
                        $orderObject->save();
                        return false;
                    }
                } else {
                    $message = "Skip order " . $orderId . " has been refunded";
                    $this->results["response"]["data"]["success"][] = $message;
                    $this->log($message);
                    return false;
                }
            }
        } catch (\Exception $e) {
            $message = $orderId . ": " . $e->getMessage();
            $this->results["response"]["data"]["error"][] = $message;
            $this->log("ERROR " . $message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($e->getMessage()), 0, 400);
        }
        return true;
    }

    /**
     * Create Credit Memo
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $refundInfo
     * @return bool
     */
    public function createCreditMemo(
        \Magento\Sales\Model\Order $order,
        array $refundInfo
    ): bool {
        $orderId = $order->getIncrementId();
        $shippingRefundedTotal = 0;
        $shippingTaxRefundedTotal = 0;
        $taxRefundedTotal = 0;
        $subtotalRefundedTotal = 0;
        $totalRefundedTotal = 0;
        foreach ($refundInfo as $_refundInfo) {
            // item info
            if (!isset($_refundInfo["item_info"]) || !count($_refundInfo["item_info"]) ||
                !isset($_refundInfo["item_info"][0])) {
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
                $sku = $itemInfo["sku"];
                $qtyRefund = (int) $itemInfo["qty"];
                foreach ($order->getAllItems() as $orderItem) {
                    if ($orderItem->getSku() == $sku) {
                        $infoItems[$orderItem->getId()] = $qtyRefund;
                    }
                }
            }
            if (!count($infoItems)) {
                $message =  "WARN create credit memo for order " . $orderId . " items ordered do not match";
                $this->results["response"]["data"]["error"][] = $message;
                $this->log($message);
                return false;
            }
            $creditMemoData = [
                'qtys' => $infoItems
            ];
            try {
                $creditMemo = $this->creditMemoFactory->createByOrder($order, $creditMemoData);
                $creditMemo->save();
                $shippingRefundedTotal += (float) $creditMemo->getData("base_shipping_amount");
                $shippingTaxRefundedTotal += (float) $creditMemo->getData("shipping_tax_amount");
                $taxRefundedTotal += (float) $creditMemo->getData("tax_amount");
                $subtotalRefundedTotal += (float) $creditMemo->getData("subtotal");
                $totalRefundedTotal += (float) $creditMemo->getData("base_grand_total");

                $message = "Credit Memo '" . $creditMemo->getIncrementId() . "' imported for order " . $orderId;
                $this->results["response"]["data"]["success"][] = $message;
                $this->log($message);

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
                return true;
            } catch (\Exception $e) {
                $errorMess = "Error while create credit memo " . $e->getMessage();
                $this->results["response"]["data"]["error"][] = $errorMess;
                $this->log("ERROR " . $errorMess);
                throw new WebapiException(__($errorMess), 0, 400);
            }
        }
        return false;
    }

    /**
     * Clean response message
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
