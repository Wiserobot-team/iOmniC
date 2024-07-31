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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\Exception as WebapiException;

class OrderIo implements \WiseRobot\Io\Api\OrderIoInterface
{
    /**
     * @var array
     */
    public array $results = [];
    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfig;
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;
    /**
     * @var OrderFactory
     */
    public $orderFactory;
    /**
     * @var OrderCollectionFactory
     */
    public $orderCollectionFactory;
    /**
     * @var PaymentConfig
     */
    public $paymentConfig;
    /**
     * @var ResourceConnection
     */
    public $resourceConnection;
    /**
     * @var SerializerInterface
     */
    public $serializer;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param OrderFactory $orderFactory
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param PaymentConfig $paymentConfig
     * @param ResourceConnection $resourceConnection
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        OrderFactory $orderFactory,
        OrderCollectionFactory $orderCollectionFactory,
        PaymentConfig $paymentConfig,
        ResourceConnection $resourceConnection,
        SerializerInterface $serializer
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->orderFactory = $orderFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->paymentConfig = $paymentConfig;
        $this->resourceConnection = $resourceConnection;
        $this->serializer = $serializer;
    }

    /**
     * Filter Order Data
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
        // create order collection
        $orderCollection = $this->orderCollectionFactory->create();
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
        $orderCollection->addFieldToFilter('main_table.store_id', $store);

        // selecting
        $orderCollection->addFieldToSelect('*');

        // join the sales_shipment, sales_shipment_track, and sales_creditmemo tables to the sales_order table
        $orderCollection->getSelect()
            ->distinct(true)
            ->joinLeft(
                ['shipment' => $this->resourceConnection->getTableName('sales_shipment')],
                'main_table.entity_id = shipment.order_id',
                ['shipment_updated_at' => 'shipment.updated_at']
            )
            ->joinLeft(
                ['shipment_track' => $this->resourceConnection->getTableName('sales_shipment_track')],
                'shipment.entity_id = shipment_track.parent_id',
                ['shipment_track_updated_at' => 'shipment_track.updated_at']
            )
            ->joinLeft(
                ['creditmemo' => $this->resourceConnection->getTableName('sales_creditmemo')],
                'main_table.entity_id = creditmemo.order_id',
                ['creditmemo_updated_at' => 'creditmemo.updated_at']
            )
            ->group('main_table.entity_id');

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

                // check if column doesn't exist in order table
                $tableName = $this->resourceConnection->getTableName(['sales_order', '']);
                if ($this->resourceConnection->getConnection()
                        ->tableColumnExists($tableName, $fieldName) !== true) {
                    $message = "Field: 'filter' - column '" .
                        $fieldName . "' doesn't exist in order table";
                    $this->results["error"] = $message;
                    throw new WebapiException(__($errorMess), 0, 400, $this->results);
                }

                // get orders where either shipment, shipment track, or credit memo was updated later than the specified time
                if ($fieldName == "updated_at") {
                    $orderCollection->addFieldToFilter(
                        ['main_table.updated_at', 'shipment.updated_at', 'shipment_track.updated_at', 'creditmemo.updated_at'],
                        [
                            [$operator => $fieldValue],
                            [$operator => $fieldValue],
                            [$operator => $fieldValue],
                            [$operator => $fieldValue]
                        ]
                    );
                } else {
                    $orderCollection->addFieldToFilter(
                        $fieldName,
                        [$operator => $fieldValue]
                    );
                }
            }
        }
        // sorting
        $orderCollection->setOrder('entity_id', 'asc');

        // paging
        $total = $orderCollection->getSize();
        if (!$page || $page <= 0) {
            $page = 1;
        }
        if (!$limit || $limit <= 0) {
            $limit = 10;
        }
        if ($limit > 100) {
            $limit = 100; // maximum page size
        }

        $result = [];
        $totalPages = ceil($total / $limit);
        if ($page > $totalPages) {
            return $result;
        }

        $orderCollection->setPageSize($limit);
        $orderCollection->setCurPage($page);
        if ($orderCollection->getSize()) {
            foreach ($orderCollection as $order) {
                $orderIId = $order->getIncrementId();
                if (!$orderIId) {
                    continue;
                }
                // order data
                $orderData = [];
                $orderData['store'] = $storeInfo->getName();
                $orderData['order_info'] = $this->getOrderInfo($order);

                // payment info
                $orderData['payment_info'] = $this->getPaymentInfo($order);

                // shipping and billing info
                $orderData['shipping_info'] = $this->getShippingInfo($order);
                $orderData['billing_info'] = $this->getBillingInfo($order);

                // order status histories comment
                $histories = $this->getStatusHistories($order);
                if (count($histories)) {
                    $orderData['status_histories'] = $histories;
                }

                // shipment info
                $shipmentInfo = $this->getShipmentInfo($order);
                if (count($shipmentInfo)) {
                    $orderData['shipment_info'] = $shipmentInfo;
                }

                // refund info
                $refundInfo = $this->getRefundInfo($order);
                if (count($refundInfo)) {
                    $orderData['refund_info'] = $refundInfo;
                }

                // item info
                $orderItems = $order->getItemsCollection();
                if ($orderItems->getSize()) {
                    $orderData['item_info'] = [];
                    foreach ($orderItems as $item) {
                        $itemData = $this->getItemInfo($item);
                        $orderData['item_info'][] = $itemData;
                    }
                }
                $result[$orderIId] = $orderData;
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
     * Get Order Info
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getOrderInfo(
        \Magento\Sales\Model\Order $order
    ): array {
        return [
            "order_id" => $order->getIncrementId(),
            "io_order_id" => $order->getData('io_order_id'),
            "ca_order_id" => $order->getData('ca_order_id'),

            // customer
            "email" => $order->getData('customer_email'),
            "firstname" => $this->formatText(
                (string) $order->getData('customer_firstname')
            ),
            "lastname" => $this->formatText(
                (string) $order->getData('customer_lastname')
            ),
            "prefix" => $this->formatText(
                (string) $order->getData('customer_prefix')
            ),
            "middlename" => $this->formatText(
                (string) $order->getData('customer_middlename')
            ),
            "suffix" => $this->formatText(
                (string) $order->getData('customer_suffix')
            ),
            "taxvat" => $order->getData('customer_taxvat'),

            // order
            "created_at" => $order->getData('created_at'),
            "updated_at" => $order->getData('updated_at'),
            "store_id" => $order->getData('store_id'),
            "entity_id" => $order->getData('entity_id'),
            "invoice_created_at" => $this->getInvoiceDate($order),
            "shipment_created_at" => $this->getShipmentDate($order),
            "creditmemo_created_at" => $this->getCreditMemoDate($order),
            "order_status" => $order->getStatus(),
            "order_state" => $order->getState(),
            "hold_before_state" => $order->getHoldBeforeState(),
            "hold_before_status" => $order->getHoldBeforeStatus(),
            "tax_amount" => $order->getData('tax_amount'),
            "base_tax_amount" => $order->getData('base_tax_amount'),
            "discount_amount" => $order->getData('discount_amount'),
            "base_discount_amount" => $order->getData('base_discount_amount'),
            "coupon_code" => $order->getData('coupon_code'),
            "subtotal" => $order->getData('subtotal'),
            "base_subtotal" => $order->getData('base_subtotal'),
            "subtotal_incl_tax" => $order->getData('subtotal_incl_tax'),
            "base_subtotal_incl_tax" => $order->getData('base_subtotal_incl_tax'),
            "grand_total" => $order->getData('grand_total'),
            "base_grand_total" => $order->getData('base_grand_total'),
            "total_paid" => $order->getData('total_paid'),
            "base_total_paid" => $order->getData('base_total_paid'),
            "total_qty_ordered" => $order->getData('total_qty_ordered'),
            "base_total_qty_ordered" => $order->getData('base_total_qty_ordered'),
            "base_currency_code" => $order->getData('base_currency_code'),
            "store_currency_code" => $order->getData('store_currency_code'),
            "order_currency_code" => $order->getData('order_currency_code'),
            "global_currency_code" => $order->getData('global_currency_code'),
            "is_virtual" => $order->getData('is_virtual'),
            "remote_ip" => $order->getData('remote_ip'),
            "weight" => $order->getData('weight'),
            "base_to_global_rate" => $order->getData('base_to_global_rate'),
            "base_to_order_rate" => $order->getData('base_to_order_rate'),
            "store_to_base_rate" => $order->getData('store_to_base_rate'),
            "store_to_order_rate" => $order->getData('store_to_order_rate'),

            // invoiced
            "discount_invoiced" => $order->getData('discount_invoiced'),
            "base_discount_invoiced" => $order->getData('base_discount_invoiced'),
            "tax_invoiced" => $order->getData('tax_invoiced'),
            "base_tax_invoiced" => $order->getData('base_tax_invoiced'),
            "subtotal_invoiced" => $order->getData('subtotal_invoiced'),
            "base_subtotal_invoiced" => $order->getData('base_subtotal_invoiced'),
            "total_invoiced" => $order->getData('total_invoiced'),
            "base_total_invoiced" => $order->getData('base_total_invoiced'),

            // shipping
            "shipping_amount" => $order->getData('shipping_amount'),
            "base_shipping_amount" => $order->getData('base_shipping_amount'),
            "shipping_tax_amount" => $order->getData('shipping_tax_amount'),
            "base_shipping_tax_amount" => $order->getData('base_shipping_tax_amount'),
            "shipping_discount_amount" => $order->getData('shipping_discount_amount'),
            "base_shipping_discount_amount" => $order->getData('base_shipping_discount_amount'),
            "shipping_invoiced" => $order->getData('shipping_invoiced'),
            "base_shipping_invoiced" => $order->getData('base_shipping_invoiced'),
            "shipping_incl_tax" => $order->getData('shipping_incl_tax'),
            "base_shipping_incl_tax" => $order->getData('base_shipping_incl_tax'),
            "shipping_canceled" => $order->getData('shipping_canceled'),
            "base_shipping_canceled" => $order->getData('base_shipping_canceled'),

            // refunded
            "shipping_refunded" => $order->getData('shipping_refunded'),
            "base_shipping_refunded" => $order->getData('base_shipping_refunded'),
            "shipping_tax_refunded" => $order->getData('shipping_tax_refunded'),
            "base_shipping_tax_refunded" => $order->getData('base_shipping_tax_refunded'),
            "tax_refunded" => $order->getData('tax_refunded'),
            "base_tax_refunded" => $order->getData('base_tax_refunded'),
            "discount_refunded" => $order->getData('discount_refunded'),
            "base_discount_refunded" => $order->getData('base_discount_refunded'),
            "subtotal_refunded" => $order->getData('subtotal_refunded'),
            "base_subtotal_refunded" => $order->getData('base_subtotal_refunded'),
            "total_refunded" => $order->getData('total_refunded'),
            "base_total_refunded" => $order->getData('base_total_refunded'),

            // canceled
            "discount_canceled" => $order->getData('discount_canceled'),
            "base_discount_canceled" => $order->getData('base_discount_canceled'),
            "tax_canceled" => $order->getData('tax_canceled'),
            "base_tax_canceled" => $order->getData('base_tax_canceled'),
            "subtotal_canceled" => $order->getData('subtotal_canceled'),
            "base_subtotal_canceled" => $order->getData('base_subtotal_canceled'),
            "total_canceled" => $order->getData('total_canceled'),
            "base_total_canceled" => $order->getData('base_total_canceled')
        ];
    }

    /**
     * Get Order Payment Info
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getPaymentInfo(
        \Magento\Sales\Model\Order $order
    ): array {
        $payment = $order->getPayment();
        $magePaymentMethods = $this->getMagentoPaymentMethods();
        if (!isset($magePaymentMethods[$payment->getData("method")])) {
            $paymentTitle = "";
        } else {
            $paymentTitle = $magePaymentMethods[$payment->getData("method")];
        }

        return [
            "payment_method" => $payment->getData("method"),
            "payment_title" => $paymentTitle,
            "cc_last4" => $payment->getData("cc_last4"),
            "cc_exp_year" => $payment->getData("cc_exp_year"),
            "cc_ss_start_month" => $payment->getData("cc_ss_start_month"),
            "cc_ss_start_year" => $payment->getData("cc_ss_start_year")
        ];
    }

    /**
     * Get Magento Payment Methods
     *
     * @return array
     */
    public function getMagentoPaymentMethods(): array
    {
        $paymentMethods = [];
        $activePayments = $this->paymentConfig->getActiveMethods();
        foreach ($activePayments as $paymentCode => $paymentModel) {
            $paymentTitle = $this->scopeConfig->getValue(
                'payment/' . $paymentCode . '/title'
            );
            if (!$paymentTitle) {
                continue;
            }
            $paymentMethods[$paymentCode] = $paymentTitle;
        }

        return $paymentMethods;
    }

    /**
     * Get Order Shipping Info
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getShippingInfo(
        \Magento\Sales\Model\Order $order
    ): array {
        $shippingAddress = !$order->getIsVirtual() ? $order->getShippingAddress() : null;
        $billingAddress = $order->getBillingAddress();
        if (!$shippingAddress) {
            $shippingAddress = $billingAddress;
        }

        return [
            "firstname" => $this->formatText(
                (string) $shippingAddress->getData("firstname")
            ),
            "lastname" => $this->formatText(
                (string) $shippingAddress->getData("lastname")
            ),
            "company" => $this->formatText(
                (string) $shippingAddress->getData("company")
            ),
            "street" => $this->formatText(
                (string) $shippingAddress->getData("street")
            ),
            "city" => $this->formatText(
                (string) $shippingAddress->getData("city")
            ),
            "region_id" => $this->formatText(
                (string) $shippingAddress->getData("region_id")
            ),
            "country_id" => $this->formatText(
                (string) $shippingAddress->getData("country_id")
            ),
            "region" => $this->formatText(
                (string) $shippingAddress->getData("region")
            ),
            "postcode" => $shippingAddress->getData("postcode"),
            "telephone" => $shippingAddress->getData("telephone"),
            "shipping_method" => $order->getShippingMethod(),
            "shipping_title" => $order->getShippingDescription()
        ];
    }

    /**
     * Get Order Shipment Info
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getShipmentInfo(
        \Magento\Sales\Model\Order $order
    ): array {
        $shipmentInfo = [];
        $shipments = $order->getShipmentsCollection();
        foreach ($shipments as $shipment) {
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
        }

        return $shipmentInfo;
    }

    /**
     * Get Order Refund Info
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getRefundInfo(
        \Magento\Sales\Model\Order $order
    ): array {
        $refundInfo = [];
        $refunds = $order->getCreditmemosCollection();
        foreach ($refunds as $refund) {
            // refund item
            $itemsData = [];
            $refundItems = $refund->getAllItems();
            foreach ($refundItems as $refundItem) {
                $itemsData[] = [
                    "sku" => $refundItem->getData("sku"),
                    "name" => $refundItem->getData("name"),
                    "price" => $refundItem->getData("price"),
                    "base_price" => $refundItem->getData("base_price"),
                    "price_incl_tax" => $refundItem->getData("price_incl_tax"),
                    "base_price_incl_tax" => $refundItem->getData("base_price_incl_tax"),
                    "qty" => $refundItem->getData("qty"),
                    "tax_amount" => $refundItem->getData("tax_amount"),
                    "base_tax_amount" => $refundItem->getData("base_tax_amount"),
                    "discount_amount" => $refundItem->getData("discount_amount"),
                    "base_discount_amount" => $refundItem->getData("base_discount_amount"),
                    "row_total" => $refundItem->getData("row_total"),
                    "base_row_total" => $refundItem->getData("base_row_total"),
                    "row_total_incl_tax" => $refundItem->getData("row_total_incl_tax"),
                    "base_row_total_incl_tax" => $refundItem->getData("base_row_total_incl_tax"),
                ];
            }
            // comment info
            $commentsData = [];
            $refundComments = $refund->getCommentsCollection();
            foreach ($refundComments as $refundComment) {
                $commentsData[] = [
                    "created_at" => $refundComment->getData("created_at"),
                    "comment" => $refundComment->getData("comment"),
                    "is_customer_notified" => $refundComment->getData("is_customer_notified")
                ];
            }

            $refundInfo[] = [
                "created_at" => $refund->getData("created_at"),
                "updated_at" => $refund->getData("updated_at"),
                "shipping_amount" => $refund->getData("shipping_amount"),
                "base_shipping_amount" => $refund->getData("base_shipping_amount"),
                "shipping_tax_amount" => $refund->getData("shipping_tax_amount"),
                "base_shipping_tax_amount" => $refund->getData("base_shipping_tax_amount"),
                "tax_amount" => $refund->getData("tax_amount"),
                "base_tax_amount" => $refund->getData("base_tax_amount"),
                "discount_amount" => $refund->getData("discount_amount"),
                "base_discount_amount" => $refund->getData("base_discount_amount"),
                "subtotal" => $refund->getData("subtotal"),
                "base_subtotal" => $refund->getData("base_subtotal"),
                "grand_total" => $refund->getData("grand_total"),
                "base_grand_total" => $refund->getData("base_grand_total"),
                "item_info" => $itemsData,
                "comment_info" => $commentsData
            ];
        }

        return $refundInfo;
    }

    /**
     * Get Order Billing Address Info
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getBillingInfo(
        \Magento\Sales\Model\Order $order
    ): array {
        return [
            "firstname" => $this->formatText(
                (string) $order->getBillingAddress()->getData("firstname")
            ),
            "lastname" => $this->formatText(
                (string) $order->getBillingAddress()->getData("lastname")
            ),
            "company" => $this->formatText(
                (string) $order->getBillingAddress()->getData("company")
            ),
            "street" => $this->formatText(
                (string) $order->getBillingAddress()->getData("street")
            ),
            "city" => $this->formatText(
                (string) $order->getBillingAddress()->getData("city")
            ),
            "region_id" => $this->formatText(
                (string) $order->getBillingAddress()->getData("region_id")
            ),
            "country_id" => $this->formatText(
                (string) $order->getBillingAddress()->getData("country_id")
            ),
            "region" => $this->formatText(
                (string) $order->getBillingAddress()->getData("region")
            ),
            "postcode" => $order->getBillingAddress()->getData("postcode"),
            "telephone" => $order->getBillingAddress()->getData("telephone")
        ];
    }

    /**
     * Get Status Histories
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getStatusHistories(
        \Magento\Sales\Model\Order $order
    ): array {
        $histories = [];
        $statusHistories = $order->getStatusHistories();
        if (count($statusHistories)) {
            foreach ($statusHistories as $statusHistory) {
                $histories[] = [
                    "comment" => $statusHistory->getData("comment"),
                    "status" => $statusHistory->getData("status"),
                    "created_at" => $statusHistory->getData("created_at"),
                    "entity_name" => $statusHistory->getData("entity_name")
                ];
            }
        }

        return $histories;
    }

    /**
     * Get Order Item Info
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @return array
     */
    public function getItemInfo(
        \Magento\Sales\Model\Order\Item $item
    ): array {
        return [
            "sku" => $this->getItemSku($item),
            "name" => $this->formatText((string) $item->getName()),
            "qty_ordered" => (int) $item->getQtyOrdered(),
            "qty_invoiced" => (int) $item->getQtyInvoiced(),
            "qty_shipped" => (int) $item->getQtyShipped(),
            "qty_refunded" => (int) $item->getQtyRefunded(),
            "qty_canceled" => (int) $item->getQtyCanceled(),
            "product_type" => $item->getProductType(),
            "price" => $item->getPrice(),
            "base_price" => $item->getBasePrice(),
            "original_price" => $item->getOriginalPrice(),
            "base_original_price" => $item->getBaseOriginalPrice(),
            "row_total" => $item->getRowTotal(),
            "base_row_total" => $item->getBaseRowTotal(),
            "row_total_incl_tax" => $item->getRowTotalInclTax(),
            "base_row_total_incl_tax" => $item->getBaseRowTotalInclTax(),
            "row_weight" => $item->getRowWeight(),
            "price_incl_tax" => $item->getPriceInclTax(),
            "base_price_incl_tax" => $item->getBasePriceInclTax(),
            "tax_amount" => $item->getTaxAmount(),
            "base_tax_amount" => $item->getBaseTaxAmount(),
            "tax_percent" => $item->getTaxPercent(),
            "discount_amount" => $item->getDiscountAmount(),
            "base_discount_amount" => $item->getBaseDiscountAmount(),
            "discount_percent" => $item->getDiscountPercent(),
            "has_parent_item" => $this->getChildInfo($item),
            "product_options" => $this->serializer->serialize(
                $item->getdata('product_options')
            )
        ];
    }

    /**
     * Format text
     *
     * @param string $string
     * @return string
     */
    public function formatText(string $string): string
    {
        $string = str_replace(',', ' ', (string) $string);

        return $string;
    }

    /**
     * Get Invoice Date
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getInvoiceDate(
        \Magento\Sales\Model\Order $order
    ): string {
        $date = '';
        $collection = $order->getInvoiceCollection();
        if (count($collection)) {
            foreach ($collection as $data) {
                $date = $data->getData('created_at');
            }
        }

        return $date;
    }

    /**
     * Get Shipment Date
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getShipmentDate(
        \Magento\Sales\Model\Order $order
    ): string {
        $date = '';
        $collection = $order->getShipmentsCollection();
        if (count($collection)) {
            foreach ($collection as $data) {
                $date = $data->getData('created_at');
            }
        }

        return $date;
    }

    /**
     * Get Credit Memo Date
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getCreditMemoDate(
        \Magento\Sales\Model\Order $order
    ): string {
        $date = '';
        $collection = $order->getCreditmemosCollection();
        if (count($collection)) {
            foreach ($collection as $data) {
                $date = $data->getData('created_at');
            }
        }

        return $date;
    }

    /**
     * Get Item Sku
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @return string
     */
    public function getItemSku(
        \Magento\Sales\Model\Order\Item $item
    ): string {
        if ($item->getProductType() == 'configurable') {
            return (string) $item->getProductOptionByCode('simple_sku');
        }

        return $item->getSku();
    }

    /**
     * Get Child Info
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @return string
     */
    public function getChildInfo(
        \Magento\Sales\Model\Order\Item $item
    ): string {
        if ($item->getParentItemId()) {
            return 'yes';
        } else {
            return 'no';
        }
    }
}
