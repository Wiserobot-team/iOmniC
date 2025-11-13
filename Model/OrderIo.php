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
use Magento\Shipping\Model\Config as ShippingConfig;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
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
     * @var array|null
     */
    public $regionCodeMapCache = null;
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
     * @var ShippingConfig
     */
    public $shippingConfig;
    /**
     * @var RegionCollectionFactory
     */
    public $regionCollectionFactory;
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
     * @param ShippingConfig $shippingConfig
     * @param RegionCollectionFactory $regionCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        OrderFactory $orderFactory,
        OrderCollectionFactory $orderCollectionFactory,
        PaymentConfig $paymentConfig,
        ShippingConfig $shippingConfig,
        RegionCollectionFactory $regionCollectionFactory,
        ResourceConnection $resourceConnection,
        SerializerInterface $serializer
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->orderFactory = $orderFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->paymentConfig = $paymentConfig;
        $this->shippingConfig = $shippingConfig;
        $this->regionCollectionFactory = $regionCollectionFactory;
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
            if (count($condition) !== 2 || empty($condition[0]) || empty($condition[1])) {
                continue;
            }
            $fieldName = $condition[0];
            $fieldValue = $condition[1];
            if (!in_array($fieldName, $columnNames)) {
                $message = "Field: 'filter' - column '{$fieldName}' doesn't exist in order table";
                $this->results["error"] = $message;
                throw new WebapiException(__($message), 0, 400, $this->results);
            }
            $operator = trim($operator);
            if (in_array($operator, ['in', 'nin'])) {
                $fieldValue = array_map('trim', explode(",", $fieldValue));
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
            "io_order_id" => $order->getData('io_order_id'),
            "site_order_id" => $order->getData('site_order_id'),
            "ca_order_id" => $order->getData('ca_order_id'),
            "entity_id" => (int) $order->getId(),
            "increment_id" => $order->getIncrementId(),
            "order_id" => $order->getIncrementId(),
            "store_id" => (int) $order->getStoreId(),
            // Customer details
            "email" => $order->getCustomerEmail(),
            "firstname" => $order->getCustomerFirstname(),
            "lastname" => $order->getCustomerLastname(),
            "prefix" => $order->getCustomerPrefix(),
            "middlename" => $order->getCustomerMiddlename(),
            "suffix" => $order->getCustomerSuffix(),
            "taxvat" => $order->getCustomerTaxvat(),
            // Order details
            "created_at" => $order->getCreatedAt(),
            "updated_at" => $order->getUpdatedAt(),
            "invoice_created_at" => $this->getInvoiceDate($order),
            "shipment_created_at" => $this->getShipmentDate($order),
            "creditmemo_created_at" => $this->getCreditMemoDate($order),
            "order_status" => $order->getStatus(),
            "order_state" => $order->getState(),
            "hold_before_state" => $order->getHoldBeforeState(),
            "hold_before_status" => $order->getHoldBeforeStatus(),
            "is_virtual" => (bool) $order->getIsVirtual(),
            "remote_ip" => $order->getRemoteIp(),
            "weight" => $order->getWeight(),
            "base_currency_code" => $order->getBaseCurrencyCode(),
            "store_currency_code" => $order->getStoreCurrencyCode(),
            "order_currency_code" => $order->getOrderCurrencyCode(),
            "global_currency_code" => $order->getGlobalCurrencyCode(),
            "base_to_global_rate" => $order->getBaseToGlobalRate(),
            "base_to_order_rate" => $order->getBaseToOrderRate(),
            "store_to_base_rate" => $order->getStoreToBaseRate(),
            "store_to_order_rate" => $order->getStoreToOrderRate(),
            "tax_amount" => $order->getTaxAmount(),
            "base_tax_amount" => $order->getBaseTaxAmount(),
            "discount_amount" => $order->getDiscountAmount(),
            "base_discount_amount" => $order->getBaseDiscountAmount(),
            "coupon_code" => $order->getCouponCode(),
            "subtotal" => $order->getSubtotal(),
            "base_subtotal" => $order->getBaseSubtotal(),
            "subtotal_incl_tax" => $order->getSubtotalInclTax(),
            "base_subtotal_incl_tax" => $order->getBaseSubtotalInclTax(),
            "grand_total" => $order->getGrandTotal(),
            "base_grand_total" => $order->getBaseGrandTotal(),
            "total_paid" => $order->getTotalPaid(),
            "base_total_paid" => $order->getBaseTotalPaid(),
            "total_qty_ordered" => (int) $order->getTotalQtyOrdered(),
            "base_total_qty_ordered" => (int) $order->getTotalQtyOrdered(),
            // Invoiced details
            "discount_invoiced" => $order->getDiscountInvoiced(),
            "base_discount_invoiced" => $order->getBaseDiscountInvoiced(),
            "tax_invoiced" => $order->getTaxInvoiced(),
            "base_tax_invoiced" => $order->getBaseTaxInvoiced(),
            "subtotal_invoiced" => $order->getSubtotalInvoiced(),
            "base_subtotal_invoiced" => $order->getBaseSubtotalInvoiced(),
            "total_invoiced" => $order->getTotalInvoiced(),
            "base_total_invoiced" => $order->getBaseTotalInvoiced(),
            "shipping_invoiced" => $order->getShippingInvoiced(),
            "base_shipping_invoiced" => $order->getBaseShippingInvoiced(),
            // Shipping details
            "shipping_amount" => $order->getShippingAmount(),
            "base_shipping_amount" => $order->getBaseShippingAmount(),
            "shipping_tax_amount" => $order->getShippingTaxAmount(),
            "base_shipping_tax_amount" => $order->getBaseShippingTaxAmount(),
            "shipping_discount_amount" => $order->getShippingDiscountAmount(),
            "base_shipping_discount_amount" => $order->getBaseShippingDiscountAmount(),
            "shipping_incl_tax" => $order->getShippingInclTax(),
            "base_shipping_incl_tax" => $order->getBaseShippingInclTax(),
            // Refunded details
            "tax_refunded" => $order->getTaxRefunded(),
            "base_tax_refunded" => $order->getBaseTaxRefunded(),
            "discount_refunded" => $order->getDiscountRefunded(),
            "base_discount_refunded" => $order->getBaseDiscountRefunded(),
            "subtotal_refunded" => $order->getSubtotalRefunded(),
            "base_subtotal_refunded" => $order->getBaseSubtotalRefunded(),
            "total_refunded" => $order->getTotalRefunded(),
            "base_total_refunded" => $order->getBaseTotalRefunded(),
            "shipping_refunded" => $order->getShippingRefunded(),
            "base_shipping_refunded" => $order->getBaseShippingRefunded(),
            "shipping_tax_refunded" => $order->getShippingTaxRefunded(),
            "base_shipping_tax_refunded" => $order->getBaseShippingTaxRefunded(),
            // Canceled details
            "discount_canceled" => $order->getDiscountCanceled(),
            "base_discount_canceled" => $order->getBaseDiscountCanceled(),
            "tax_canceled" => $order->getTaxCanceled(),
            "base_tax_canceled" => $order->getBaseTaxCanceled(),
            "subtotal_canceled" => $order->getSubtotalCanceled(),
            "base_subtotal_canceled" => $order->getBaseSubtotalCanceled(),
            "total_canceled" => $order->getTotalCanceled(),
            "base_total_canceled" => $order->getBaseTotalCanceled(),
            "shipping_canceled" => $order->getShippingCanceled(),
            "base_shipping_canceled" => $order->getBaseShippingCanceled()
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
        $paymentMethod = $payment->getMethod();
        $paymentTitle = $magePaymentMethods[$paymentMethod] ?? "";
        return [
            "payment_method" => $paymentMethod,
            "payment_title" => $paymentTitle,
            "cc_last4" => $payment->getCcLast4(),
            "cc_exp_year" => $payment->getCcExpYear(),
            "cc_ss_start_month" => $payment->getCcSsStartMonth(),
            "cc_ss_start_year" => $payment->getCcSsStartYear()
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
        $shippingRegionId = $shippingAddress ? $shippingAddress->getData('region_id') : null;
        if ($shippingRegionId) {
            $shippingRegionId = $this->getRegionCodeById((int) $shippingRegionId);
        }
        return [
            "firstname" => $shippingAddress ? $shippingAddress->getData("firstname") : null,
            "lastname" => $shippingAddress ? $shippingAddress->getData("lastname") : null,
            "company" => $shippingAddress ? $shippingAddress->getData("company") : null,
            "street" => $shippingAddress ? $shippingAddress->getData("street") : null,
            "city" => $shippingAddress ? $shippingAddress->getData("city") : null,
            "region_id" => $shippingRegionId,
            "country_id" => $shippingAddress ? $shippingAddress->getData("country_id") : null,
            "region" => $shippingAddress ? $shippingAddress->getData("region") : null,
            "postcode" => $shippingAddress ? $shippingAddress->getData("postcode") : null,
            "telephone" => $shippingAddress ? $shippingAddress->getData("telephone") : null,
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
        $billingRegionId = $billingAddress ? $billingAddress->getData('region_id') : null;
        if ($billingRegionId) {
            $billingRegionId = $this->getRegionCodeById((int) $billingRegionId);
        }
        return [
            "firstname" => $billingAddress ? $billingAddress->getData("firstname") : null,
            "lastname" => $billingAddress ? $billingAddress->getData("lastname") : null,
            "company" => $billingAddress ? $billingAddress->getData("company") : null,
            "street" => $billingAddress ? $billingAddress->getData("street") : null,
            "city" => $billingAddress ? $billingAddress->getData("city") : null,
            "region_id" => $billingRegionId,
            "country_id" => $billingAddress ? $billingAddress->getData("country_id") : null,
            "region" => $billingAddress ? $billingAddress->getData("region") : null,
            "postcode" => $billingAddress ? $billingAddress->getData("postcode") : null,
            "telephone" => $billingAddress ? $billingAddress->getData("telephone") : null
        ];
    }

    /**
     * Get Region Code By Id
     *
     * @param int|null $regionId
     * @return string
     */
    public function getRegionCodeById(?int $regionId): string
    {
        if (!$regionId) {
            return '';
        }
        if ($this->regionCodeMapCache === null) {
            $this->regionCodeMapCache = [];
            $regionCollection = $this->regionCollectionFactory->create();
            $regionCollection->addFieldToSelect(['region_id', 'code']);
            foreach ($regionCollection as $region) {
                $this->regionCodeMapCache[(int)$region->getId()] = $region->getCode();
            }
        }
        return $this->regionCodeMapCache[$regionId] ?? (string)$regionId;
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
        $order = $item->getOrder();
        $parentItem = $item->getParentItem();
        $parentItemInfo = null;
        $componentLookupMap = [];
        $calculatedParentInfo = [];
        if ($order && $parentItem) {
            $componentLookupMap = $this->createComponentLookupMap($order);
        }
        if ($parentItem && $parentItem instanceof \Magento\Sales\Model\Order\Item) {
            $parentItemInfo = $this->getParentItemInfo(
                $parentItem,
                $componentLookupMap,
                $calculatedParentInfo
            );
        }
        $parentItemId = $item->getParentItemId();
        $processedParentItemId = empty($parentItemId) ? null : (int) $parentItemId;
        return [
            "item_id" => (int) $item->getItemId(),
            "parent_item_id" => $processedParentItemId,
            "order_id" => (int) $item->getOrderId(),
            "store_id" => (int) $item->getStoreId(),
            "sku" => $this->getItemSku($item),
            "name" => $item->getName(),
            "qty_ordered" => (int) $item->getQtyOrdered(),
            "qty_invoiced" => (int) $item->getQtyInvoiced(),
            "qty_shipped" => (int) $item->getQtyShipped(),
            "qty_refunded" => (int) $item->getQtyRefunded(),
            "qty_canceled" => (int) $item->getQtyCanceled(),
            "price" => $item->getPrice(),
            "base_price" => $item->getBasePrice(),
            "original_price" => $item->getOriginalPrice(),
            "base_original_price" => $item->getBaseOriginalPrice(),
            "price_incl_tax" => $item->getPriceInclTax(),
            "base_price_incl_tax" => $item->getBasePriceInclTax(),
            "row_total" => $item->getRowTotal(),
            "base_row_total" => $item->getBaseRowTotal(),
            "row_total_incl_tax" => $item->getRowTotalInclTax(),
            "base_row_total_incl_tax" => $item->getBaseRowTotalInclTax(),
            "row_weight" => $item->getRowWeight(),
            "tax_amount" => $item->getTaxAmount(),
            "base_tax_amount" => $item->getBaseTaxAmount(),
            "tax_percent" => $item->getTaxPercent(),
            "discount_amount" => $item->getDiscountAmount(),
            "base_discount_amount" => $item->getBaseDiscountAmount(),
            "discount_percent" => $item->getDiscountPercent(),
            "product_options" => $this->serializer->serialize(
                $item->getProductOptions()
            ),
            "product_type" => $item->getProductType(),
            "parent_item" => $parentItemInfo
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
        $componentLookupMap = $this->createComponentLookupMap($order);
        $calculatedParentInfo = [];
        foreach ($order->getShipmentsCollection() as $shipment) {
            $itemsData = [];
            foreach ($shipment->getItemsCollection() as $shipmentItem) {
                $productType = null;
                $parentItemInfo = null;
                $orderItem = $shipmentItem->getOrderItem();
                if ($orderItem) {
                    $productType = $orderItem->getProductType();
                    $parentOrderItem = $orderItem->getParentItem();
                    if ($parentOrderItem) {
                        $parentItemInfo = $this->getParentItemInfo(
                            $parentOrderItem,
                            $componentLookupMap,
                            $calculatedParentInfo
                        );
                    }
                }
                $itemsData[] = [
                    "entity_id" => (int) $shipmentItem->getId(),
                    "parent_id" => (int) $shipmentItem->getParentId(),
                    "order_item_id" => (int) $shipmentItem->getOrderItemId(),
                    "sku" => $shipmentItem->getSku(),
                    "name" => $shipmentItem->getName(),
                    "price" => $shipmentItem->getPrice(),
                    "qty" => (int) $shipmentItem->getQty(),
                    "weight" => $shipmentItem->getWeight(),
                    "product_type" => $productType,
                    "parent_item" => $parentItemInfo
                ];
            }
            $tracksData = [];
            foreach ($shipment->getTracksCollection() as $shipmentTrack) {
                $tracksData[] = [
                    "entity_id" => (int) $shipmentTrack->getId(),
                    "parent_id" => (int) $shipmentTrack->getParentId(),
                    "order_id" => (int) $shipmentTrack->getOrderId(),
                    "created_at" => $shipmentTrack->getCreatedAt(),
                    "updated_at" => $shipmentTrack->getUpdatedAt(),
                    "carrier_code" => $shipmentTrack->getCarrierCode(),
                    "title" => $shipmentTrack->getTitle(),
                    "track_number" => $shipmentTrack->getTrackNumber()
                ];
            }
            $shipmentInfo[] = [
                "entity_id" => (int) $shipment->getId(),
                "increment_id" => $shipment->getIncrementId(),
                "order_id" => (int) $shipment->getOrderId(),
                "store_id" => (int) $shipment->getStoreId(),
                "created_at" => $shipment->getCreatedAt(),
                "updated_at" => $shipment->getUpdatedAt(),
                "total_qty" => (int) $shipment->getTotalQty(),
                "total_weight" => $shipment->getTotalWeight(),
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
        $componentLookupMap = $this->createComponentLookupMap($order);
        $calculatedParentInfo = [];
        foreach ($order->getCreditmemosCollection() as $refund) {
            $itemsData = [];
            foreach ($refund->getAllItems() as $refundItem) {
                $productType = null;
                $parentItemInfo = null;
                $orderItem = $refundItem->getOrderItem();
                if ($orderItem) {
                    $productType = $orderItem->getProductType();
                    $parentOrderItem = $orderItem->getParentItem();
                    if ($parentOrderItem) {
                        $parentItemInfo = $this->getParentItemInfo(
                            $parentOrderItem,
                            $componentLookupMap,
                            $calculatedParentInfo
                        );
                    }
                }
                $itemsData[] = [
                    "entity_id" => (int) $refundItem->getId(),
                    "parent_id" => (int) $refundItem->getParentId(),
                    "order_item_id" => (int) $refundItem->getOrderItemId(),
                    "sku" => $refundItem->getSku(),
                    "name" => $refundItem->getName(),
                    "qty" => (int) $refundItem->getQty(),
                    "price" => $refundItem->getPrice(),
                    "base_price" => $refundItem->getBasePrice(),
                    "price_incl_tax" => $refundItem->getPriceInclTax(),
                    "base_price_incl_tax" => $refundItem->getBasePriceInclTax(),
                    "tax_amount" => $refundItem->getTaxAmount(),
                    "base_tax_amount" => $refundItem->getBaseTaxAmount(),
                    "discount_amount" => $refundItem->getDiscountAmount(),
                    "base_discount_amount" => $refundItem->getBaseDiscountAmount(),
                    "row_total" => $refundItem->getRowTotal(),
                    "base_row_total" => $refundItem->getBaseRowTotal(),
                    "row_total_incl_tax" => $refundItem->getRowTotalInclTax(),
                    "base_row_total_incl_tax" => $refundItem->getBaseRowTotalInclTax(),
                    "product_type" => $productType,
                    "parent_item" => $parentItemInfo
                ];
            }
            $commentsData = [];
            foreach ($refund->getCommentsCollection() as $refundComment) {
                $commentsData[] = [
                    "entity_id" => (int) $refundComment->getId(),
                    "parent_id" => (int) $refundComment->getParentId(),
                    "created_at" => $refundComment->getCreatedAt(),
                    "updated_at" => $refundComment->getUpdatedAt(),
                    "comment" => $refundComment->getComment(),
                    "is_customer_notified" => $refundComment->getIsCustomerNotified(),
                    "is_visible_on_front" => $refundComment->getIsVisibleOnFront()
                ];
            }
            $refundInfo[] = [
                "entity_id" => (int) $refund->getId(),
                "increment_id" => $refund->getIncrementId(),
                "order_id" => (int) $refund->getOrderId(),
                "store_id" => (int) $refund->getStoreId(),
                "created_at" => $refund->getCreatedAt(),
                "updated_at" => $refund->getUpdatedAt(),
                "shipping_amount" => $refund->getShippingAmount(),
                "base_shipping_amount" => $refund->getBaseShippingAmount(),
                "shipping_tax_amount" => $refund->getShippingTaxAmount(),
                "base_shipping_tax_amount" => $refund->getBaseShippingTaxAmount(),
                "shipping_incl_tax" => $refund->getShippingInclTax(),
                "base_shipping_incl_tax" => $refund->getBaseShippingInclTax(),
                "tax_amount" => $refund->getTaxAmount(),
                "base_tax_amount" => $refund->getBaseTaxAmount(),
                "discount_amount" => $refund->getDiscountAmount(),
                "base_discount_amount" => $refund->getBaseDiscountAmount(),
                "subtotal" => $refund->getSubtotal(),
                "base_subtotal" => $refund->getBaseSubtotal(),
                "subtotal_incl_tax" => $refund->getSubtotalInclTax(),
                "base_subtotal_incl_tax" => $refund->getBaseSubtotalInclTax(),
                "grand_total" => $refund->getGrandTotal(),
                "base_grand_total" => $refund->getBaseGrandTotal(),
                "item_info" => $itemsData,
                "comment_info" => $commentsData
            ];
        }
        return $refundInfo;
    }

    /**
     * Create Component Lookup Map
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function createComponentLookupMap(
        \Magento\Sales\Model\Order $order
    ): array {
        $componentLookupMap = [];
        foreach ($order->getAllItems() as $item) {
            if ($item->getParentItem() && $item->getParentItem()->getProductType() === 'bundle') {
                $parentId = $item->getParentItem()->getItemId();
                $itemProductOptions = $item->getProductOptions();
                if (!empty($itemProductOptions) && is_array($itemProductOptions)) {
                    if (isset($itemProductOptions['bundle_selection_attributes'])) {
                        $selectionAttrString = $itemProductOptions['bundle_selection_attributes'];
                        try {
                            $selectionAttr = $this->serializer->unserialize($selectionAttrString);
                        } catch (\Exception $e) {
                            continue;
                        }
                        if (isset($selectionAttr['option_id'])) {
                            $optionId = (string) $selectionAttr['option_id'];
                            if (!isset($componentLookupMap[$parentId])) {
                                $componentLookupMap[$parentId] = [];
                            }
                            $componentLookupMap[$parentId][$optionId] = [
                                'sku' => $item->getSku(),
                                'price' => $item->getPrice()
                            ];
                        }
                    }
                }
            }
        }
        return $componentLookupMap;
    }

    /**
     * Get Parent Item Info
     *
     * @param \Magento\Sales\Model\Order\Item $parentOrderItem
     * @param array $componentLookupMap
     * @param array $cache
     * @return array
     */
    public function getParentItemInfo(
        \Magento\Sales\Model\Order\Item $parentOrderItem,
        array $componentLookupMap,
        array &$cache
    ): array {
        $parentId = $parentOrderItem->getItemId();
        if (isset($cache[$parentId])) {
            return $cache[$parentId];
        }
        $parentProductOptions = $parentOrderItem->getProductOptions();
        $originalOptions = [];
        $modifiedOptions = [];
        if (!empty($parentProductOptions) && is_array($parentProductOptions)) {
            $originalOptions = $parentProductOptions;
            $modifiedOptions = $originalOptions;
            if (isset($modifiedOptions['bundle_options']) && isset($componentLookupMap[$parentId])) {
                foreach ($modifiedOptions['bundle_options'] as $optionId => &$optionData) {
                    if (
                        isset($optionData['value']) && is_array($optionData['value']) &&
                        isset($componentLookupMap[$parentId][(string)$optionId])
                    ) {
                        $lookupData = $componentLookupMap[$parentId][(string)$optionId];
                        foreach ($optionData['value'] as &$selectionValue) {
                            $selectionValue['sku'] = $lookupData['sku'];
                            $selectionValue['component_unit_price'] = $lookupData['price'];
                        }
                        unset($selectionValue);
                    }
                }
                unset($optionData);
            }
        }
        $parentItemInfo = [
            "item_id" => (int) $parentOrderItem->getItemId(),
            "order_id" => (int) $parentOrderItem->getOrderId(),
            "sku" => $this->getItemSku($parentOrderItem),
            "name" => $parentOrderItem->getName(),
            "qty_ordered" => (int) $parentOrderItem->getQtyOrdered(),
            "product_type" => $parentOrderItem->getProductType(),
            "product_options" => $this->serializer->serialize(
                $originalOptions
            ),
            "product_options_extended" => $this->serializer->serialize(
                $modifiedOptions
            )
        ];
        $cache[$parentId] = $parentItemInfo;
        return $parentItemInfo;
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
                "entity_id" => (int) $statusHistory->getId(),
                "parent_id" => (int) $statusHistory->getParentId(),
                "created_at" => $statusHistory->getCreatedAt(),
                "comment" => $statusHistory->getComment(),
                "status" => $statusHistory->getStatus(),
                "entity_name" => $statusHistory->getEntityName(),
                "is_customer_notified" => $statusHistory->getIsCustomerNotified(),
                "is_visible_on_front" => $statusHistory->getIsVisibleOnFront()
            ];
        }
        return $histories;
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
        return $firstItem && $firstItem->getCreatedAt()
            ? $firstItem->getCreatedAt()
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
        $activeCarriers = $this->shippingConfig->getAllCarriers();
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
