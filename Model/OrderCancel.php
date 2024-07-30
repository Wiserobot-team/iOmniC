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
use Magento\Framework\Webapi\Exception as WebapiException;

class OrderCancel implements \WiseRobot\Io\Api\OrderCancelInterface
{
    /**
     * @var string
     */
    public $logFile = "wr_io_order_cancel.log";
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
     * @param Filesystem $filesystem
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        Filesystem $filesystem,
        OrderFactory $orderFactory
    ) {
        $this->filesystem = $filesystem;
        $this->orderFactory = $orderFactory;
    }

    /**
     * Cancel Order
     *
     * @param string $orderId
     * @return array
     */
    public function cancel(string $orderId): array
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

        try {
            $this->cancelOrder($orderId);
            $this->cleanResponseMessages();
            return $this->results;
        } catch (\Exception $e) {
            $errorMess = "order cancellation error";
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }
    }

    /**
     * Cancel the order using the order id
     *
     * @param string $orderId
     * @return bool
     */
    public function cancelOrder(string $orderId): bool
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

            if ($order->getStatus() == "canceled") {
                $message = "Skip order " . $orderId . " has been canceled";
                $this->results["response"]["data"]["success"][] = $message;
                $this->log($message);
                return false;
            }

            // cancel the order
            $order->cancel();
            $order->setData("status", "canceled");
            $order->setData("state", "canceled");
            $order->save();

            $message = "Order " . $orderId . " has been successfully canceled";
            $this->results["response"]["data"]["success"][] = $message;
            $this->log($message);
            return true;
        } catch (\Exception $e) {
            $message = $orderId . ": " . $e->getMessage();
            $this->results["response"]["data"]["error"][] = $message;
            $this->log("ERROR " . $message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($e->getMessage()), 0, 400);
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
