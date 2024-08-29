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
     * @var Zend_Log
     */
    public $logger;
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
        $this->initializeResults();
        $this->initializeLogger();
    }

    /**
     * Import Refund
     *
     * @param string $incrementId
     * @param mixed $refundInfo
     * @return array
     */
    public function import(string $incrementId, mixed $refundInfo): array
    {
        $this->validateRefundInfo($incrementId, $refundInfo);
        $this->importIoRefund($incrementId, $refundInfo);
        $this->cleanResponseMessages();
        return $this->results;
    }

    /**
     * Validate Refund Info
     *
     * @param string $incrementId
     * @param mixed $refundInfo
     * @return void
     */
    public function validateRefundInfo(string $incrementId, mixed $refundInfo): void
    {
        if (empty($incrementId)) {
            $this->addMessageAndLog("Field: 'order_id' is a required field", "error");
        }
        if (empty($refundInfo) || !is_array($refundInfo)) {
            $this->addMessageAndLog("Field: 'refund_info' is a required field", "error");
        }
        foreach ($refundInfo as $refund) {
            if (empty($refund["refund_date"]) || empty($refund["refund_status"])
                || empty($refund["item_info"]) || !is_array($refund["item_info"])) {
                $this->addMessageAndLog("Field: 'refund_info' - incorrect data", "error");
            }
            foreach ($refund["item_info"] as $item) {
                if (empty($item["sku"]) || empty($item["qty"])) {
                    $this->addMessageAndLog("Field: 'item_info' - {'sku','qty'} data fields are required", "error");
                }
            }
        }
    }

    /**
     * Import Io Refund
     *
     * @param string $incrementId
     * @param array $refundInfo
     * @return bool
     */
    public function importIoRefund(string $incrementId, array $refundInfo): bool
    {
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
        if (!$order || !$order->getId()) {
            $this->addMessageAndLog("Cannot load order {$incrementId}", "error");
        }
        if ($order->getStatus() == "closed") {
            $this->addMessageAndLog("Order {$incrementId} has been closed", "success");
            return true;
        }
        if (!$order->hasInvoices()) {
            $this->addMessageAndLog("Order {$incrementId} does not have an invoice", "error");
        }
        if ($order->hasCreditmemos()) {
            $this->addMessageAndLog("Order {$incrementId} has been refunded", 'success');
            return true;
        }
        $this->createCreditMemo($order, $refundInfo);
        $this->updateOrderStatus($incrementId, 'closed');
        return true;
    }

    /**
     * Update Order Status
     *
     * @param string $incrementId
     * @param string $status
     * @return void
     */
    public function updateOrderStatus(string $incrementId, string $status): void
    {
        try {
            $orderObject = $this->orderFactory->create()->loadByIncrementId($incrementId);
            if ($orderObject->getId() && $orderObject->getStatus() !== $status) {
                $orderObject->setData("status", $status);
                $orderObject->setData("state", $status);
                $orderObject->save();
                $this->addMessageAndLog("Order {$incrementId} status set to {$status} success", 'success');
            }
        } catch (\Exception $e) {
            $this->addMessageAndLog("ERROR set status {$e->getMessage()}", "error");
        }
    }

    /**
     * Create Credit Memo
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $refundInfo
     * @return void
     */
    public function createCreditMemo(
        \Magento\Sales\Model\Order $order,
        array $refundInfo
    ): void {
        try {
            $incrementId = $order->getIncrementId();
            $shippingRefundedTotal = 0;
            $shippingTaxRefundedTotal = 0;
            $taxRefundedTotal = 0;
            $subtotalRefundedTotal = 0;
            $totalRefundedTotal = 0;
            foreach ($refundInfo as $refund) {
                $refundItems = $this->getRefundItems($order, $refund["item_info"]);
                if (empty($refundItems)) {
                    $this->addMessageAndLog("Order {$incrementId} items ordered do not match for order", "error");
                }
                $creditMemoData = ['qtys' => $refundItems];
                $creditMemo = $this->creditMemoFactory->createByOrder($order, $creditMemoData);
                $creditMemo->save();
                $this->addMessageAndLog("Credit Memo '{$creditMemo->getIncrementId()}' imported for order {$incrementId}", "success");
                $shippingRefundedTotal += (float) $creditMemo->getData("base_shipping_amount");
                $shippingTaxRefundedTotal += (float) $creditMemo->getData("shipping_tax_amount");
                $taxRefundedTotal += (float) $creditMemo->getData("tax_amount");
                $subtotalRefundedTotal += (float) $creditMemo->getData("subtotal");
                $totalRefundedTotal += (float) $creditMemo->getData("base_grand_total");
            }
            // Update refunded data for order
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
        } catch (\Exception $e) {
            $this->addMessageAndLog("ERROR create credit memo {$e->getMessage()}", "error");
        }
    }

    /**
     * Get Credit Memo Items
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $itemInfo
     * @return array
     */
    public function getRefundItems(\Magento\Sales\Model\Order $order, array $itemInfo): array
    {
        $refundItems = [];
        foreach ($itemInfo as $item) {
            $sku = $item["sku"];
            $qtyRefund = (int) $item["qty"];
            foreach ($order->getAllItems() as $orderItem) {
                if ($orderItem->getSku() == $sku) {
                    $refundItems[$orderItem->getId()] = $qtyRefund;
                }
            }
        }
        return $refundItems;
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
