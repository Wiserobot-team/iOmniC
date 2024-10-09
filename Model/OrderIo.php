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
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Shipping\Model\Config as ShippingConfig;
use Magento\Directory\Model\RegionFactory;
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
     * @var OrderRepositoryInterface
     */
    public $orderRepository;
    /**
     * @var PaymentConfig
     */
    public $paymentConfig;
    /**
     * @var ShippingConfig
     */
    public $shippingConfig;
    /**
     * @var RegionFactory
     */
    public $regionFactory;
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
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentConfig $paymentConfig
     * @param ShippingConfig $shippingConfig
     * @param RegionFactory $regionFactory
     * @param ResourceConnection $resourceConnection
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        OrderFactory $orderFactory,
        OrderCollectionFactory $orderCollectionFactory,
        OrderRepositoryInterface $orderRepository,
        PaymentConfig $paymentConfig,
        ShippingConfig $shippingConfig,
        RegionFactory $regionFactory,
        ResourceConnection $resourceConnection,
        SerializerInterface $serializer
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->orderFactory = $orderFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderRepository = $orderRepository;
        $this->paymentConfig = $paymentConfig;
        $this->shippingConfig = $shippingConfig;
        $this->regionFactory = $regionFactory;
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
        $storeInfo = $this->getStoreInfo($store);
        $orderCollection = $this->createOrderCollection($store);
        $this->applyFilter($orderCollection, $filter);
        $this->applySortingAndPaging($orderCollection, $page, $limit);
        $result = [];
        $storeName = $storeInfo->getName();
        foreach ($orderCollection as $order) {
            $incrementId = $order->getIncrementId();
            if ($incrementId) {
                $orderData = $this->formatOrderData($order);
                if (!empty($orderData)) {
                    $orderData['store'] = $storeName;
                    $result[$incrementId] = $orderData;
                }
            }
        }
        return $result;
    }

    /**
     * Get Order by ID
     *
     * @param int $orderId
     * @return array
     */
    public function getById(int $orderId): array
    {
        return $this->getOrder($orderId, 'id');
    }

    /**
     * Get Order by increment ID
     *
     * @param string $incrementId
     * @return array
     */
    public function getByIncrementId(string $incrementId): array
    {
        return $this->getOrder($incrementId);
    }

    /**
     * Get Order
     *
     * @param int|string $id
     * @param string $typeId
     * @return array
     */
    public function getOrder(int|string $id, string $typeId = 'incrementId'): array
    {
        $typeId === "id"
        ? $order = $this->orderFactory->create()->load($id)
        : $order = $this->orderFactory->create()->loadByIncrementId($id);
        if (!$order || !$order->getId()) {
            return [];
        }
        $orderData = $this->formatOrderData($order);
        return !empty($orderData) ? [$id => $orderData] : [];
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
     * Create order collection with basic filters
     *
     * @param int $store
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    public function createOrderCollection(
        int $store
    ): \Magento\Sales\Model\ResourceModel\Order\Collection {
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addFieldToFilter('main_table.store_id', $store)
            ->addFieldToSelect('*')
            ->getSelect()
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
        return $orderCollection;
    }

    /**
     * Apply filter to the order collection
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\Collection $orderCollection
     * @param string $filter
     */
    public function applyFilter(
        \Magento\Sales\Model\ResourceModel\Order\Collection $orderCollection,
        string $filter
    ): void {
        $filter = trim((string) $filter);
        $filterArray = explode(" and ", (string) $filter);
        $tableName = $this->resourceConnection->getTableName('sales_order');
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
                $message = "Field: 'filter' - column '{$fieldName}' doesn't exist in order table";
                $this->results["error"] = $message;
                throw new WebapiException(__($message), 0, 400, $this->results);
            }
            if ($fieldName === "updated_at") {
                $orderCollection->addFieldToFilter(
                    [
                        'main_table.updated_at',
                        'shipment.updated_at',
                        'shipment_track.updated_at',
                        'creditmemo.updated_at'
                    ],
                    [
                        [$operator => $fieldValue],
                        [$operator => $fieldValue],
                        [$operator => $fieldValue],
                        [$operator => $fieldValue]
                    ]
                );
            } else {
                $orderCollection->addFieldToFilter(
                    'main_table.' . $fieldName,
                    [$operator => $fieldValue]
                );
            }
        }
    }

    /**
     * Apply sorting and paging to the order collection
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\Collection $orderCollection
     * @param int $page
     * @param int $limit
     */
    public function applySortingAndPaging(
        \Magento\Sales\Model\ResourceModel\Order\Collection $orderCollection,
        int $page,
        int $limit
    ): void {
        $orderCollection->setOrder('entity_id', 'asc')
            ->setPageSize(min(max(1, (int) $limit), 100))
            ->setCurPage(max(1, (int) $page));
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
     * Get Order Data
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function formatOrderData(
        \Magento\Sales\Model\Order $order
    ): array {
        $orderData = [
            'order_info' => $this->getOrderInfo($order),
            'payment_info' => $this->getPaymentInfo($order),
            'shipping_info' => $this->getShippingInfo($order),
            'billing_info' => $this->getBillingInfo($order)
        ];
        if ($order->getItemsCollection()->getSize()) {
            $orderData['item_info'] = array_values(array_map(
                fn ($item) => $this->getItemInfo($item),
                $order->getItemsCollection()->getItems()
            ));
        }
        $histories = $this->getStatusHistories($order);
        if (!empty($histories)) {
            $orderData['status_histories'] = $histories;
        }
        $shipmentInfo = $this->getShipmentInfo($order);
        if (!empty($shipmentInfo)) {
            $orderData['shipment_info'] = $shipmentInfo;
        }
        $refundInfo = $this->getRefundInfo($order);
        if (!empty($refundInfo)) {
            $orderData['refund_info'] = $refundInfo;
        }
        return $orderData;
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
            // Basic order details
            "order_id" => $order->getIncrementId(),
            "io_order_id" => $order->getData('io_order_id'),
            "ca_order_id" => $order->getData('ca_order_id'),
            // Customer details
            "email" => $order->getData('customer_email'),
            "firstname" => $order->getData('customer_firstname'),
            "lastname" => $order->getData('customer_lastname'),
            "prefix" => $order->getData('customer_prefix'),
            "middlename" => $order->getData('customer_middlename'),
            "suffix" => $order->getData('customer_suffix'),
            "taxvat" => $order->getData('customer_taxvat'),
            // Order details
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
            // Invoiced details
            "discount_invoiced" => $order->getData('discount_invoiced'),
            "base_discount_invoiced" => $order->getData('base_discount_invoiced'),
            "tax_invoiced" => $order->getData('tax_invoiced'),
            "base_tax_invoiced" => $order->getData('base_tax_invoiced'),
            "subtotal_invoiced" => $order->getData('subtotal_invoiced'),
            "base_subtotal_invoiced" => $order->getData('base_subtotal_invoiced'),
            "total_invoiced" => $order->getData('total_invoiced'),
            "base_total_invoiced" => $order->getData('base_total_invoiced'),
            // Shipping details
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
            // Refunded details
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
            // Canceled details
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
        $paymentMethod = $payment->getData("method");
        $paymentTitle = $magePaymentMethods[$paymentMethod] ?? "";
        return [
            "payment_method" => $paymentMethod,
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
            $paymentTitle = $this->scopeConfig->getValue("payment/$paymentCode/title");
            if ($paymentTitle) {
                $paymentMethods[$paymentCode] = $paymentTitle;
            }
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
        $shippingAddress = $order->getShippingAddress();
        if ($shippingRegionId = $shippingAddress->getData('region_id')) {
            $shippingRegion = $this->regionFactory->create()->load($shippingRegionId);
            $shippingRegionId = $shippingRegion->getId() ? $shippingRegion->getCode() : $shippingRegionId;
        }
        return [
            "firstname" => $shippingAddress->getData("firstname"),
            "lastname" => $shippingAddress->getData("lastname"),
            "company" => $shippingAddress->getData("company"),
            "street" => $shippingAddress->getData("street"),
            "city" => $shippingAddress->getData("city"),
            "region_id" => $shippingRegionId,
            "country_id" => $shippingAddress->getData("country_id"),
            "region" => $shippingAddress->getData("region"),
            "postcode" => $shippingAddress->getData("postcode"),
            "telephone" => $shippingAddress->getData("telephone"),
            "shipping_method" => $order->getShippingMethod(),
            "shipping_title" => $order->getShippingDescription()
        ];
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
        $billingAddress = $order->getBillingAddress();
        if ($billingRegionId = $billingAddress->getData('region_id')) {
            $billingRegion = $this->regionFactory->create()->load($billingRegionId);
            $billingRegionId = $billingRegion->getId() ? $billingRegion->getCode() : $billingRegionId;
        }
        return [
            "firstname" => $billingAddress->getData("firstname"),
            "lastname" => $billingAddress->getData("lastname"),
            "company" => $billingAddress->getData("company"),
            "street" => $billingAddress->getData("street"),
            "city" => $billingAddress->getData("city"),
            "region_id" => $billingRegionId,
            "country_id" => $billingAddress->getData("country_id"),
            "region" => $billingAddress->getData("region"),
            "postcode" => $billingAddress->getData("postcode"),
            "telephone" => $billingAddress->getData("telephone")
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
        foreach ($order->getShipmentsCollection() as $shipment) {
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
        foreach ($order->getCreditmemosCollection() as $refund) {
            $itemsData = [];
            foreach ($refund->getAllItems() as $refundItem) {
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
            $commentsData = [];
            foreach ($refund->getCommentsCollection() as $refundComment) {
                $commentsData[] = [
                    "created_at" => $refundComment->getData("created_at"),
                    "comment" => $refundComment->getData("comment"),
                    "is_customer_notified" => $refundComment->getData("is_customer_notified")
                ];
            }
            $refundInfo[] = [
                "created_at" => $refund->getData("created_at"),
                "updated_at" => $refund->getData("updated_at"),
                "store_id" => $refund->getData("store_id"),
                "entity_id" => $refund->getData("entity_id"),
                "increment_id" => $refund->getData("increment_id"),
                "order_id" => $refund->getData("order_id"),
                "shipping_amount" => $refund->getData("shipping_amount"),
                "base_shipping_amount" => $refund->getData("base_shipping_amount"),
                "shipping_tax_amount" => $refund->getData("shipping_tax_amount"),
                "base_shipping_tax_amount" => $refund->getData("base_shipping_tax_amount"),
                "shipping_incl_tax" => $refund->getData("shipping_incl_tax"),
                "base_shipping_incl_tax" => $refund->getData("base_shipping_incl_tax"),
                "tax_amount" => $refund->getData("tax_amount"),
                "base_tax_amount" => $refund->getData("base_tax_amount"),
                "discount_amount" => $refund->getData("discount_amount"),
                "base_discount_amount" => $refund->getData("base_discount_amount"),
                "subtotal" => $refund->getData("subtotal"),
                "base_subtotal" => $refund->getData("base_subtotal"),
                "subtotal_incl_tax" => $refund->getData("subtotal_incl_tax"),
                "base_subtotal_incl_tax" => $refund->getData("base_subtotal_incl_tax"),
                "grand_total" => $refund->getData("grand_total"),
                "base_grand_total" => $refund->getData("base_grand_total"),
                "item_info" => $itemsData,
                "comment_info" => $commentsData
            ];
        }
        return $refundInfo;
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
        foreach ($order->getStatusHistories() as $statusHistory) {
            $histories[] = [
                "comment" => $statusHistory->getData("comment"),
                "status" => $statusHistory->getData("status"),
                "created_at" => $statusHistory->getData("created_at"),
                "entity_name" => $statusHistory->getData("entity_name")
            ];
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
            "name" => $item->getName(),
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
                $item->getData('product_options')
            )
        ];
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
        return $this->getFirstItemCreatedAt($order->getInvoiceCollection());
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
        return $this->getFirstItemCreatedAt($order->getShipmentsCollection());
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
        return $this->getFirstItemCreatedAt($order->getCreditmemosCollection());
    }

    /**
     * Get the creation date from the first item of a given collection
     *
     * @param \Magento\Framework\Data\Collection\AbstractDb $collection
     * @return string
     */
    public function getFirstItemCreatedAt(
        \Magento\Framework\Data\Collection\AbstractDb $collection
    ): string {
        $firstItem = $collection->getFirstItem();
        return $firstItem && $firstItem->getData('created_at')
            ? $firstItem->getData('created_at')
            : '';
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
        return $item->getProductType() === 'configurable'
            ? (string) $item->getProductOptionByCode('simple_sku')
            : $item->getSku();
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
        return $item->getParentItemId() ? 'yes' : 'no';
    }

    /**
     * Get Payment Methods
     *
     * @return array
     */
    public function getPaymentMethods(): array
    {
        $paymentMethodArray = [];
        $payments = $this->paymentConfig->getActiveMethods();
        foreach ($payments as $paymentCode => $paymentModel) {
            $paymentTitle = $this->scopeConfig->getValue("payment/$paymentCode/title");
            if ($paymentTitle) {
                $paymentMethodArray[] = [$paymentCode => $paymentTitle];
            }
        }
        return $paymentMethodArray;
    }

    /**
     * Get Shipping Methods
     *
     * @return array
     */
    public function getShippingMethods(): array
    {
        $shipMethods = [];
        $activeCarriers = $this->shippingConfig->getActiveCarriers();
        foreach ($activeCarriers as $carrierCode => $carrierModel) {
            $carrierTitle = $this->scopeConfig->getValue("carriers/$carrierCode/title");
            $carrierMethods = $carrierModel->getAllowedMethods();
            if ($carrierMethods) {
                foreach ($carrierMethods as $methodCode => $methodLabel) {
                    if (is_array($methodLabel)) {
                        foreach ($methodLabel as $methodLabelKey => $methodLabelValue) {
                            $shipMethods[] = [$methodLabelKey => $methodLabelValue];
                        }
                    } else {
                        $shipMethod = $carrierCode . "_" . $methodCode;
                        $shipMethodTitle = $carrierTitle . " - " . $methodLabel;
                        $shipMethods[] = [$shipMethod => $shipMethodTitle];
                    }
                }
            }
        }
        return $shipMethods;
    }

    /**
     * Get Shipping Carriers
     *
     * @return array
     */
    public function getShippingCarriers(): array
    {
        $storeId = 0;
        $carriers = [
            ["custom" => __("Custom Value")]
        ];
        $carrierInstances = $this->shippingConfig->getAllCarriers($storeId);
        foreach ($carrierInstances as $code => $carrier) {
            if ($carrier->isTrackingAvailable()) {
                $carriers[] = [$code => $carrier->getConfigData("title")];
            }
        }
        return $carriers;
    }
}
