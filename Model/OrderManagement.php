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
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Quote\Model\Quote\Address\Rate as AddressRate;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order\ItemFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Sales\Model\Order\PaymentFactory;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Sales\Model\Order\AddressFactory;
use Magento\Sales\Api\InvoiceManagementInterface;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Convert\Order as ConvertOrder;
use Magento\Sales\Model\Order\Shipment\TrackFactory as ShipmentTrackFactory;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Framework\Event\Manager as EventManager;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Shipping\Model\Config as ShippingConfig;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Framework\Webapi\Exception as WebapiException;
use WiseRobot\Io\Model\IoOrderFactory;
use WiseRobot\Io\Helper\Sku as SkuHelper;

class OrderManagement implements \WiseRobot\Io\Api\OrderManagementInterface
{
    /**
     * @var Zend_Log
     */
    public $logger;
    /**
     * @var string
     */
    public $logFile = "wr_io_order_import.log";
    /**
     * @var bool
     */
    public $isTaxInclusive = false;
    /**
     * @var bool
     */
    public $originalPriceInclTax = false;
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
     * @var Filesystem
     */
    public $filesystem;
    /**
     * @var CustomerFactory
     */
    public $customerFactory;
    /**
     * @var CustomerRepositoryInterface
     */
    public $customerRepository;
    /**
     * @var ProductFactory
     */
    public $productFactory;
    /**
     * @var AddressRate
     */
    public $shippingRate;
    /**
     * @var CartManagementInterface
     */
    public $cartManagementInterface;
    /**
     * @var CartRepositoryInterface
     */
    public $cartRepositoryInterface;
    /**
     * @var ItemFactory
     */
    public $orderItemFactory;
    /**
     * @var OrderFactory
     */
    public $orderFactory;
    /**
     * @var RegionFactory
     */
    public $regionFactory;
    /**
     * @var PaymentFactory
     */
    public $paymentFactory;
    /**
     * @var AddressInterfaceFactory
     */
    public $addressInterfaceFactory;
    /**
     * @var AddressFactory
     */
    public $orderAddressFactory;
    /**
     * @var InvoiceManagementInterface
     */
    public $invoiceManagementInterface;
    /**
     * @var Transaction
     */
    public $transaction;
    /**
     * @var ConvertOrder
     */
    public $convertOrder;
    /**
     * @var ShipmentTrackFactory
     */
    public $shipmentTrackFactory;
    /**
     * @var CreditmemoFactory
     */
    public $creditMemoFactory;
    /**
     * @var EventManager
     */
    public $eventManager;
    /**
     * @var ProductRepositoryInterface
     */
    public $productRepository;
    /**
     * @var ShippingConfig
     */
    public $shippingConfig;
    /**
     * @var PaymentConfig
     */
    public $paymentConfig;
    /**
     * @var IoOrderFactory
     */
    public $ioOrderFactory;
    /**
     * @var SkuHelper
     */
    public $skuHelper;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Filesystem $filesystem
     * @param CustomerFactory $customerFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param ProductFactory $productFactory
     * @param AddressRate $shippingRate
     * @param CartManagementInterface $cartManagementInterface
     * @param CartRepositoryInterface $cartRepositoryInterface
     * @param ItemFactory $orderItemFactory
     * @param OrderFactory $orderFactory
     * @param RegionFactory $regionFactory
     * @param PaymentFactory $paymentFactory
     * @param AddressInterfaceFactory $addressInterfaceFactory
     * @param AddressFactory $orderAddressFactory
     * @param InvoiceManagementInterface $invoiceManagementInterface
     * @param Transaction $transaction
     * @param ConvertOrder $convertOrder
     * @param ShipmentTrackFactory $shipmentTrackFactory
     * @param CreditmemoFactory $creditMemoFactory
     * @param EventManager $eventManager
     * @param ProductRepositoryInterface $productRepository
     * @param ShippingConfig $shippingConfig
     * @param PaymentConfig $paymentConfig
     * @param IoOrderFactory $ioOrderFactory
     * @param SkuHelper $skuHelper
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        CustomerFactory $customerFactory,
        CustomerRepositoryInterface $customerRepository,
        ProductFactory $productFactory,
        AddressRate $shippingRate,
        CartManagementInterface $cartManagementInterface,
        CartRepositoryInterface $cartRepositoryInterface,
        ItemFactory $orderItemFactory,
        OrderFactory $orderFactory,
        RegionFactory $regionFactory,
        PaymentFactory $paymentFactory,
        AddressInterfaceFactory $addressInterfaceFactory,
        AddressFactory $orderAddressFactory,
        InvoiceManagementInterface $invoiceManagementInterface,
        Transaction $transaction,
        ConvertOrder $convertOrder,
        ShipmentTrackFactory $shipmentTrackFactory,
        CreditmemoFactory $creditMemoFactory,
        EventManager $eventManager,
        ProductRepositoryInterface $productRepository,
        ShippingConfig $shippingConfig,
        PaymentConfig $paymentConfig,
        IoOrderFactory $ioOrderFactory,
        SkuHelper $skuHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->productFactory = $productFactory;
        $this->shippingRate = $shippingRate;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->orderItemFactory = $orderItemFactory;
        $this->orderFactory = $orderFactory;
        $this->regionFactory = $regionFactory;
        $this->paymentFactory = $paymentFactory;
        $this->addressInterfaceFactory = $addressInterfaceFactory;
        $this->orderAddressFactory = $orderAddressFactory;
        $this->invoiceManagementInterface = $invoiceManagementInterface;
        $this->transaction = $transaction;
        $this->convertOrder = $convertOrder;
        $this->shipmentTrackFactory = $shipmentTrackFactory;
        $this->creditMemoFactory = $creditMemoFactory;
        $this->eventManager = $eventManager;
        $this->productRepository = $productRepository;
        $this->shippingConfig = $shippingConfig;
        $this->paymentConfig = $paymentConfig;
        $this->ioOrderFactory = $ioOrderFactory;
        $this->skuHelper = $skuHelper;
        $this->initializeResults();
        $this->initializeLogger();
    }

    /**
     * Import Order by Order Data
     *
     * @param int $store
     * @param string[] $orderInfo
     * @param string[] $paymentInfo
     * @param string[] $shippingInfo
     * @param string[] $billingInfo
     * @param mixed $itemInfo
     * @param mixed $statusHistories
     * @param mixed $shipmentInfo
     * @param mixed $refundInfo
     * @return array
     */
    public function import(
        int $store,
        array $orderInfo,
        array $paymentInfo,
        array $shippingInfo,
        array $billingInfo,
        mixed $itemInfo,
        mixed $statusHistories = [],
        mixed $shipmentInfo = [],
        mixed $refundInfo = []
    ): array {
        // store info
        if (!$store) {
            $this->handleValidationError("Field: 'store' is a required field");
        }
        try {
            $storeInfo = $this->storeManager->getStore($store);
        } catch (\Exception $e) {
            $this->handleValidationError("Requested 'store' " . $store . " doesn't exist");
        }

        $validationRules = [
            "order_info" => [
                2 => [
                    "io_order_id", "order_time_gmt", "email", "item_sale_source",
                    "checkout_status", "shipping_status", "refund_status"
                ],
                3 => [
                    "grand_total", "tax_amount", "shipping_amount",
                    "shipping_tax_amount", "discount_amount"
                ]
            ],
            "payment_info" => [
                1 => ["cc_last4"],
                2 => ["payment_method"]
            ],
            "shipping_info" => [
                1 => [
                    "firstname", "lastname", "company", "street", "city", "region_id",
                    "country_id", "region", "postcode", "telephone", "shipping_method"
                ]
            ],
            "billing_info" => [
                1 => [
                    "firstname", "lastname", "company", "street", "city", "region_id",
                    "country_id", "region", "postcode", "telephone"
                ]
            ],
            "item_info" => [
                1 => ["id"],
                2 => ["sku", "name"],
                3 => ["price", "qty", "tax_percent", "tax_amount", "weight"]
            ]
        ];
        foreach ($validationRules as $fieldName => $rules) {
            $camelCaseName = $this->snakeToCamelCase($fieldName);
            $data = $$camelCaseName ?? null;
            if ($fieldName === "item_info") {
                if (empty($data)) {
                    $this->handleValidationError("Field: 'item_info' is a required field");
                }
                foreach ($data as $item) {
                    foreach ($rules as $type => $fields) {
                        if ($message = $this->validateFields($item, $fieldName, $fields, $type)) {
                            $this->handleValidationError($message);
                        }
                    }
                }
            } else {
                foreach ($rules as $type => $fields) {
                    if ($message = $this->validateFields($data, $fieldName, $fields, $type)) {
                        $this->handleValidationError($message);
                    }
                }
            }
        }

        // import io order
        try {
            $this->importIoOrder(
                $orderInfo,
                $paymentInfo,
                $shippingInfo,
                $billingInfo,
                $statusHistories,
                $shipmentInfo,
                $refundInfo,
                $itemInfo,
                $storeInfo
            );
            $this->cleanResponseMessages();
            return $this->results;
        } catch (\Exception $e) {
            $errorMess = "order import error";
            throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
        }
    }

    /**
     * Validates fields
     *
     * @param array|null $data
     * @param string $fieldName
     * @param array $fields
     * @param int $type
     * @return string|null
     */
    public function validateFields(
        ?array $data,
        string $fieldName,
        array $fields,
        int $type = 1
    ): ?string {
        if (!$data) {
            return "Field: '{$fieldName}' is a required field";
        }
        $validators = [
            1 => fn($field) => !isset($data[$field]),
            2 => fn($field) => empty($data[$field]),
            3 => fn($field) => !isset($data[$field]) || !is_numeric($data[$field])
        ];
        $errorFields = array_filter($fields, $validators[$type] ?? fn() => false);
        return $errorFields
            ? "Field: '{$fieldName}' - {" . implode(", ", $errorFields) . "} data fields are required"
            : null;
    }

    /**
     * Converts a snake_case string to camelCase.
     *
     * @param string $string
     * @return string
     */
    public function snakeToCamelCase(string $string): string
    {
        return preg_replace_callback(
            '/_([a-z])/',
            function ($matches) {
                return strtoupper($matches[1]);
            },
            $string
        );
    }

    /**
     * Handles validation errors
     *
     * @param string $message
     * @return void
     */
    public function handleValidationError(string $message): void
    {
        $errorMess = "Data request error";
        $this->results["response"]["data"]["error"][] = $message;
        $this->log("ERROR: " . $message);
        $this->cleanResponseMessages();
        throw new WebapiException(__($errorMess), 0, 400, $this->results["response"]);
    }

    /**
     * Import Io Order
     *
     * @param array $orderInfo
     * @param array $paymentInfo
     * @param array $shippingInfo
     * @param array $billingInfo
     * @param array $statusHistories
     * @param array $shipmentInfo
     * @param array $refundInfo
     * @param array $itemInfo
     * @param mixed $store
     * @return mixed
     */
    public function importIoOrder(
        array $orderInfo,
        array $paymentInfo,
        array $shippingInfo,
        array $billingInfo,
        array $statusHistories,
        array $shipmentInfo,
        array $refundInfo,
        array $itemInfo,
        mixed $store
    ): bool {
        $storeId = (int) $store->getId();
        $ioOrderId = $orderInfo["io_order_id"];
        $siteOrderId = $orderInfo["site_order_id"] ?? '';
        $caOrderId = $orderInfo["ca_order_id"] ?? '';
        $buyerUserID = $orderInfo["buyer_user_id"] ?? $orderInfo["email"];
        $itemSaleSource = $orderInfo["item_sale_source"];

        $this->isTaxInclusive = !empty($orderInfo["order_tax_type"]);
        $this->originalPriceInclTax = !empty($orderInfo["original_price_type"]);

        try {
            // order items
            $orderItemsWeightTotal = 0;
            $orderItemsQtyTotal = 0;
            $orderSubtotal = 0;

            if (!is_array($itemInfo)) {
                $itemInfo = [$itemInfo];
            }

            $ioOrderItems = [];
            foreach ($itemInfo as $orderItem) {
                $sku = $orderItem["sku"];
                if (!isset($ioOrderItems[$sku])) {
                    $ioOrderItems[$sku] = $orderItem;
                }
            }

            // check if an order already exists
            $oldIoOrder = $this->ioOrderFactory->create();
            $oldIoOrder->load($ioOrderId, "io_order_id");

            $isOldOrder = false;
            $skuIsMissing = false;
            $refundStatus = $orderInfo["refund_status"];
            if ($oldIoOrder->getId()) {
                $oldOrderIId = $oldIoOrder->getData("order_increment_id");
                $oldOrder = $this->orderFactory->create()->loadByIncrementId($oldOrderIId);
                if (!$oldOrder || !$oldOrder->getId()) {
                    $message = "WARN cannot load order <" . $oldOrderIId . ">";
                    $this->results["response"]["data"]["error"][] = $message;
                    $this->log($message);
                    return false;
                }
                if ($oldOrder->getStatus() == "closed") {
                    $message = "Skip order " . $ioOrderId . " update to <" .
                        $oldOrderIId . "> for order has been closed";
                    $this->results["response"]["data"]["success"][] = $message;
                    $this->log($message);
                    return false;
                }
                if ($oldOrder->hasShipments()) {
                    $message = "Skip order " . $ioOrderId . " update to <" .
                        $oldOrderIId . "> for order has been shipped";
                    $this->results["response"]["data"]["success"][] = $message;
                    $this->log($message);
                    // create credit memo for order
                    if ($refundStatus && $refundStatus != "unrefunded" && count($refundInfo)) {
                        $this->createCreditMemo($oldOrderIId, $refundInfo);
                        $orderObject = $this->orderFactory->create()
                            ->loadByIncrementId($oldOrder->getIncrementId());
                        if ($orderObject->hasShipments() && $orderObject->getStatus() != "closed" &&
                            in_array($orderObject->getStatus(), ["complete"])) {
                            $orderObject->setData("status", "closed");
                            $orderObject->setData("state", "closed");
                            $orderObject->save();
                        }
                    }
                    return false;
                } else {
                    // check and create credit memo for order
                    if ($refundStatus && $refundStatus != "unrefunded" && count($refundInfo)) {
                        $this->createCreditMemo($oldOrderIId, $refundInfo);
                        $orderObject = $this->orderFactory->create()
                            ->loadByIncrementId($oldOrder->getIncrementId());
                        if (!$orderObject->hasShipments() && $orderObject->getStatus() != "closed" &&
                            in_array($orderObject->getStatus(), ["processing"])) {
                            $orderObject->setData("status", "closed");
                            $orderObject->setData("state", "closed");
                            $orderObject->save();
                        }
                        return false;
                    }
                    if ($oldOrder->getStatus() == "canceled") {
                        $message = "Skip order " . $ioOrderId . " update to <" .
                            $oldOrderIId . "> for order has been canceled";
                        $this->results["response"]["data"]["success"][] = $message;
                        $this->log($message);
                        return false;
                    }
                    if ($orderInfo["checkout_status"] != "canceled") {
                        // create shipment for order
                        if ($orderInfo["shipping_status"] == "shipped") {
                            $this->createShipment($oldOrder, $orderInfo, $shipmentInfo);
                            return false;
                        }
                        $message = "Order " . $ioOrderId . " updated into <" . $oldOrderIId . ">";
                        $this->results["response"]["data"]["success"][] = $message;
                        $this->log($message);
                        return false;
                    }
                }
                $isOldOrder = true;
                $cart = $oldOrder;
            } else {
                $skuIsMissing = $this->isSkuInOrderMissing($ioOrderItems, $storeId);
                if ($skuIsMissing) {
                    // create order object if the SKU not found, bypass checkout flow
                    $cart = $this->orderFactory->create();
                } else {
                    // create cart object if no SKU missing, like normally checkout
                    $cartId = $this->cartManagementInterface->createEmptyCart();
                    $cart = $this->cartRepositoryInterface->get($cartId);
                }
                $cart->setStoreId($storeId);
            }

            // shipping, billing address info
            if (!$isOldOrder) {
                if ($skuIsMissing) {
                    $shippingAddress = $this->getShippingAddress($shippingInfo, $storeId, 0);
                    $billingAddress = $this->getBillingAddress($billingInfo, $shippingInfo, $storeId, 0);
                } else {
                    $shippingAddress = $this->getShippingAddress($shippingInfo, $storeId);
                    $billingAddress = $this->getBillingAddress($billingInfo, $shippingInfo, $storeId);
                }
            } else {
                $shippingAddress = $this->getShippingAddress($shippingInfo, $storeId, 0);
                $billingAddress = $this->getBillingAddress($billingInfo, $shippingInfo, $storeId, 0);
            }

            $customerEmail = $orderInfo["email"];

            // find customer in magento
            $customer = $this->customerFactory->create();
            $customer->setWebsiteId($store->getWebsiteId())
                     ->loadByEmail($customerEmail);

            if (trim((string) $shippingInfo["lastname"]) == "") {
                $lastnameValid = " unknown";
            } else {
                $lastnameValid = $shippingInfo["lastname"];
            }

            if (trim((string) $shippingInfo["firstname"]) == "") {
                $firstnameValid = $lastnameValid;
            } else {
                $firstnameValid = $shippingInfo["firstname"];
            }

            if ($customer->getEntityId()) { // if existing customer
                $customer = $this->customerRepository->getById($customer->getEntityId());
                // update customer info
                if (!$customer->getFirstname() || !$customer->getLastname()) {
                    $customer->setFirstname($firstnameValid);
                    $customer->setLastname($lastnameValid);
                    try {
                        $this->customerRepository->save($customer);
                    } catch (\Exception $e) {
                        $message = "Order " . $ioOrderId . ": update customer " . $e->getMessage();
                        $this->results["response"]["data"]["error"][] = $message;
                        $this->log("ERROR " . $message);
                        $this->cleanResponseMessages();
                        throw new WebapiException(__($e->getMessage()), 0, 400);
                    }
                }
                if (!$isOldOrder && !$skuIsMissing) {
                    $cart->assignCustomer($customer);
                }
                $cart->setCustomerIsGuest(0)
                    ->setCustomerId($customer->getId())
                    ->setCustomerGroupId($customer->getGroupId())
                    ->setCustomerFirstname($customer->getFirstname())
                    ->setCustomerLastname($customer->getLastname())
                    ->setCustomerEmail($customer->getEmail());
            } else { // else no customer exist
                $cart->setCustomerIsGuest(1)
                    ->setCustomerFirstname($firstnameValid)
                    ->setCustomerLastname($lastnameValid)
                    ->setCustomerGroupId(0)
                    ->setCustomerEmail($customerEmail);
            }

            // item data will not be changed for already imported order
            if (!$isOldOrder) {
                foreach ($ioOrderItems as $orderItem) {
                    // add order item
                    $webOrderItem = $this->getOrderItem($orderItem, $storeId);
                    if (!$webOrderItem) {
                        $this->results["response"]["data"]["error"][] = "order " . $ioOrderId . " has invalid item";
                        $this->log("WARN order " . $ioOrderId . " has invalid item");
                        return false;
                    }
                    $orderItemsWeightTotal += (float) $orderItem["weight"];
                    $orderItemsQtyTotal += (int) $orderItem["qty"];
                    if (!empty($orderInfo["order_subtotal_type"])) {
                        $orderSubtotal += ((float) $orderItem["qty"] * $webOrderItem->getData('price_incl_tax'));
                    } else {
                        $orderSubtotal += ((float) $orderItem["qty"] * $webOrderItem->getData('price'));
                    }

                    if ($skuIsMissing) {
                        $cart->addItem($webOrderItem);
                        continue;
                    }
                    $product = $this->productRepository->getById(
                        $webOrderItem['product_id'],
                        false,
                        $store->getId()
                    );

                    try {
                        if ($product->getTypeId() == "bundle") {
                            $params = [
                                'product' => $product->getId(),
                                'bundle_option' => $this->getBundleOptions($product),
                                'qty' => (int) $webOrderItem['qty_ordered']
                            ];
                            $cart->addProduct($product, new \Magento\Framework\DataObject($params));
                            continue;
                        }

                        $item = $cart->addProduct($product, (int) $webOrderItem['qty_ordered']);
                        if (is_string($item)) {
                            $message = "Order " . $ioOrderId . " product '" . $product->getSku() . "': " . $item;
                            $this->results["response"]["data"]["error"][] = $message;
                            $this->log("ERROR " . $message);
                            $this->eventManager->dispatch(
                                'io_order_import_error',
                                [
                                    'order_client_id' => $ioOrderId,
                                    'sku' => $product->getSku(),
                                    'product_id' => $product->getId(),
                                    'error' => $message
                                ]
                            );
                            return false;
                        }
                    } catch (\Exception $e) {
                        $message = "Order " . $ioOrderId . " product '" .
                            $product->getSku() . "': " . $e->getMessage();
                        $this->results["response"]["data"]["error"][] = $message;
                        $this->log("ERROR " . $message);
                        $this->cleanResponseMessages();
                        $this->eventManager->dispatch(
                            'io_order_import_error',
                            [
                                'order_client_id' => $ioOrderId,
                                'sku' => $product->getSku(),
                                'product_id' => $product->getId(),
                                'error' => $message
                            ]
                        );
                        throw new WebapiException(__($e->getMessage()), 0, 400);
                    }
                }
            }

            // set shipping, billing address to cart
            if (!$isOldOrder) {
                if ($skuIsMissing) {
                    $shippingAddress->setOrder($cart);
                    $billingAddress->setOrder($cart);
                    $cart->setShippingAddress($shippingAddress);
                    $cart->setBillingAddress($billingAddress);
                } else {
                    $cart->getShippingAddress()->addData($shippingAddress->getData());
                    $cart->getBillingAddress()->addData($billingAddress->getData());
                }
            } else {
                $shippingAddress->setOrder($cart);
                $billingAddress->setOrder($cart);
                $cart->setShippingAddress($shippingAddress);
                $cart->setBillingAddress($billingAddress);
            }

            // set shipping method
            $mageShippingMethods = $this->getMagentoShippingMethods();
            $defaultShippingMethod = key($mageShippingMethods);
            $ioShippingMethod = $shippingInfo["shipping_method"];
            if (!$ioShippingMethod || !isset($mageShippingMethods[$ioShippingMethod])) {
                $ioShippingMethod = $defaultShippingMethod;
            }
            $ioShippingDescription = $mageShippingMethods[$ioShippingMethod];
            if ($ioShippingMethod && $ioShippingDescription) {
                if (!$isOldOrder) {
                    if ($skuIsMissing) {
                        $cart->setData("shipping_method", $ioShippingMethod);
                        $cart->setData("shipping_description", $ioShippingDescription);
                    } else {
                        $this->shippingRate->setCode($ioShippingMethod)
                                           ->getPrice(1);
                        $shippingAddress = $cart->getShippingAddress();
                        $shippingAddress->setCollectShippingRates(true)
                                        ->collectShippingRates();
                        $cart->setTotalsCollectedFlag(false)
                             ->collectTotals();
                        $shippingAddress->setShippingMethod($ioShippingMethod)
                            ->setShippingDescription($ioShippingDescription);
                        $cart->getShippingAddress()->addShippingRate($this->shippingRate);
                    }
                } else {
                    $cart->setData("shipping_method", $ioShippingMethod);
                    $cart->setData("shipping_description", $ioShippingDescription);
                }
            }

            // add payment for new order only
            if (!$isOldOrder) {
                $paymentInfo = $this->getPaymentInfo($paymentInfo);
                if ($skuIsMissing) {
                    $cart->setPayment($paymentInfo);
                } else {
                    $cart->setPaymentMethod($paymentInfo->getData("method"));
                    $cart->setInventoryProcessed(false);
                    $orderPayment = [
                        "method" => $paymentInfo->getData("method"),
                        "cc_last4" => $paymentInfo->getData("cc_last4"),
                    ];
                    $cart->getPayment()->addData($orderPayment);
                }
            }

            $cart->setCanSendNewEmailFlag(false);
            if (!$isOldOrder) {
                if ($skuIsMissing) {
                    $newOrder = $cart;
                } else {
                    $cart->collectTotals();
                    $cart->save();
                    $cart = $this->cartRepositoryInterface->get($cart->getId());
                    // set prefix
                    if (!empty($orderInfo["order_increment_id"])) {
                        $reserveOrderId = $cart->reserveOrderId();
                        $newIncrementId = $reserveOrderId->getReservedOrderId();
                        if ($newIncrementId != trim((string) $orderInfo["order_increment_id"])) {
                            $newIncrementId = trim((string) $orderInfo["order_increment_id"]);
                            $reserveOrderId->setReservedOrderId($newIncrementId);
                        }
                    }
                    $orderID = $this->cartManagementInterface->placeOrder($cart->getId());
                    $newOrder = $this->orderFactory->create()->load($orderID);
                }
            } else {
                $newOrder = $cart;
            }

            // re-add shipping method if order is created by cart
            if (!$isOldOrder && !$skuIsMissing && $ioShippingMethod && $ioShippingDescription) {
                $newOrder->setData("shipping_method", $ioShippingMethod);
                $newOrder->setData("shipping_description", $ioShippingDescription);
            }

            // update order items
            /*if (!$isOldOrder && !$skuIsMissing) {
                foreach ($ioOrderItems as $orderItem) {
                    $webOrderItem = $this->getOrderItem($orderItem, $storeId);
                    $mageOrderItems = $newOrder->getItemsCollection();
                    if (!$mageOrderItems) {
                        continue;
                    }
                    foreach ($mageOrderItems as $mageOrderItem) {
                        $productId = $mageOrderItem->getProductId();
                        if ($productId && $productId == $webOrderItem['product_id']) {
                            $updateItem = $this->updateOrderItemCalculation($mageOrderItem, $webOrderItem);
                            $updateItem->save();
                        }
                    }
                }
            }*/

            // order status info
            if ($orderInfo["checkout_status"] == "canceled") {
                $status = "canceled";
                $state = "canceled";
            } elseif ($orderInfo["checkout_status"] == "completed") {
                $status = "processing";
                $state = "processing";
            } else {
                $status = "pending";
                $state = "new";
            }
            $newOrder->setData("status", $status);
            $newOrder->setData("state", $state);

            // shipping info
            $shippingAmount = $orderInfo["shipping_amount"] ? : 0;
            $newOrder->setData("base_shipping_amount", $shippingAmount);
            $newOrder->setData("shipping_amount", $shippingAmount);
            $newOrder->setData("base_shipping_incl_tax", $shippingAmount);
            $newOrder->setData("shipping_incl_tax", $shippingAmount);

            // order invoice info
            if (!$isOldOrder) {
                $newOrder->setData("grand_total", $orderInfo["grand_total"]);
                $newOrder->setData("base_grand_total", $orderInfo["grand_total"]);
                $newOrder->setData("weight", $orderItemsWeightTotal);
                $newOrder->setData("subtotal", $orderSubtotal);
                $newOrder->setData("base_subtotal", $orderSubtotal);
                $newOrder->setData("subtotal_incl_tax", $orderSubtotal);
                $newOrder->setData("base_subtotal_incl_tax", $orderSubtotal);
                $newOrder->setData("tax_amount", $orderInfo["tax_amount"]);
                $newOrder->setData("base_tax_amount", $orderInfo["tax_amount"]);
                if (!empty($orderInfo["shipping_tax_type"])) {
                    $shippingCost = (float) $orderInfo["shipping_amount"] - $orderInfo["shipping_tax_amount"];
                } else {
                    $shippingCost = (float) $orderInfo["shipping_amount"];
                }
                $newOrder->setData("base_shipping_amount", $shippingCost);
                $newOrder->setData("shipping_amount", $shippingCost);
                $newOrder->setData("shipping_tax_amount", $orderInfo["shipping_tax_amount"]);
                $newOrder->setData("base_shipping_tax_amount", $orderInfo["shipping_tax_amount"]);
                $currencyCode = $store->getCurrentCurrencyCode();
                $newOrder->setData('base_currency_code', $currencyCode);
                $newOrder->setData('store_currency_code', $currencyCode);
                $newOrder->setData('order_currency_code', $currencyCode);
                $newOrder->setData('global_currency_code', $currencyCode);
                $newOrder->setData("is_virtual", 0);
                $newOrder->setData("total_qty_ordered", $orderItemsQtyTotal);
                $newOrder->setData("base_total_qty_ordered", $orderItemsQtyTotal);
                $newOrder->setData("discount_amount", $orderInfo["discount_amount"]);
                $newOrder->setData("base_discount_amount", $orderInfo["discount_amount"]);
                $newOrder->setData("base_to_global_rate", 1);
                if ($orderInfo["order_time_gmt"]) {
                    $newOrder->setData("created_at", $orderInfo["order_time_gmt"]);
                }
            }

            // set custom order columns
            $newOrder->setData("io_order_id", $ioOrderId);
            $newOrder->setData("site_order_id", $siteOrderId);
            $newOrder->setData("ca_order_id", $caOrderId);
            $newOrder->setData("buyer_user_id", $buyerUserID);
            $newOrder->setData("io_marketplace", $itemSaleSource);

            // add comment
            if (!$isOldOrder) {
                $statusMessage  = "Marketplace: " . $itemSaleSource . ", Rithum Order ID: " .
                    $caOrderId . ", Site Order ID: " . $siteOrderId . ", Buyer User ID: " . $buyerUserID;
                $newOrder->addStatusHistoryComment($statusMessage);
            }

            // status histories
            if (count($statusHistories)) {
                foreach ($statusHistories as $statusHistory) {
                    if (!isset($statusHistory["comment"]) || !$statusHistory["comment"]) {
                        continue;
                    }
                    $commentString = $statusHistory["comment"];
                    $orderComments = $newOrder->getAllStatusHistory();
                    // check if order has the comment already
                    $addedComment  = false;
                    foreach ($orderComments as $orderComment) {
                        $existingComment = $orderComment->getData("comment");
                        if ($existingComment == $commentString) {
                            $addedComment = true;
                            break;
                        }
                    }
                    if ($addedComment == false) {
                        $newOrder->addStatusHistoryComment($commentString);
                    }
                }
            }

            if (!empty($orderInfo["rep_user_name"])) {
                $newOrder->setData("rep_user_name", trim((string) $orderInfo["rep_user_name"]));
            }

            // save order
            $savedOrder = $newOrder->save();
            if (!$isOldOrder && $skuIsMissing) {
                // set prefix
                if (!empty($orderInfo["order_increment_id"])) {
                    $newIncrementId = $savedOrder->getIncrementId();
                    if ($newIncrementId != trim((string) $orderInfo["order_increment_id"])) {
                        $newIncrementId = trim((string) $orderInfo["order_increment_id"]);
                        $savedOrder = $newOrder->setIncrementId($newIncrementId)->save();
                    }
                }
            }

            // save order id, io order id, marketplace
            $oldIoOrder->setData("order_increment_id", $newOrder->getIncrementId());
            $oldIoOrder->setData("io_order_id", $ioOrderId);
            $oldIoOrder->setData("marketplace", $itemSaleSource);
            if ($status == "canceled" || $state == "canceled") {
                $oldIoOrder->setData("status", $status);
                $oldIoOrder->setData("state", $state);
            }
            $oldIoOrder->save();

            if ($isOldOrder) {
                if ($status == "canceled" || $state == "canceled") {
                    $message = "Order " . $ioOrderId . " updated into <" .
                        $savedOrder->getIncrementId() . "> has been canceled";
                    $this->results["response"]["data"]["success"][] = $message;
                    $this->log($message);
                } else {
                    $message = "Order " . $ioOrderId . " updated into <" . $savedOrder->getIncrementId() . ">";
                    $this->results["response"]["data"]["success"][] = $message;
                    $this->log($message);
                }
            } else {
                $message = "Order " . $ioOrderId . " imported into <" . $savedOrder->getIncrementId() . ">";
                $this->results["response"]["data"]["success"][] = $message;
                $this->log($message);
            }

            if ($state != "canceled") {
                // create invoice for order
                $this->createInvoice($savedOrder);
                // create shipment for order
                if ($orderInfo["shipping_status"] == "shipped") {
                    $this->createShipment($savedOrder, $orderInfo, $shipmentInfo);
                }
            }

            // create credit memo for order
            if ($refundStatus && $refundStatus != "unrefunded" && count($refundInfo)) {
                $this->createCreditMemo($savedOrder->getIncrementId(), $refundInfo);
            }
            // create order completed
            return true;
        } catch (\Exception $e) {
            $message = "Order " . $ioOrderId . ": " . $e->getMessage();
            $this->results["response"]["data"]["error"][] = $message;
            $this->log("ERROR " . $message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($e->getMessage()), 0, 400);
        }
    }

    /**
     * Get Order Shipping Address
     *
     * @param array $shippingInfo
     * @param int $storeId
     * @param int $create
     * @return mixed
     */
    public function getShippingAddress(
        array $shippingInfo,
        int $storeId,
        int $create = 1
    ): mixed {
        $countryCode = $shippingInfo["country_id"];
        if ($shippingInfo["country_id"] == "PR") {
            $countryCode = "US";
            $shippingInfo["region_id"] = "PR";
        }
        $data = [
            "firstname" => $shippingInfo["firstname"],
            "lastname" => $shippingInfo["lastname"],
            "company" => $shippingInfo["company"],
            "street" => $shippingInfo["street"],
            "city" => $shippingInfo["city"],
            "region_id" => $shippingInfo["region_id"],
            "country_id" => $countryCode,
            "region" => $shippingInfo["region"],
            "postcode" => $shippingInfo["postcode"],
            "telephone" => $shippingInfo["telephone"],
        ];

        if (trim((string) $data["telephone"]) == "") {
            $data["telephone"] = 0000;
        }
        if (trim((string) $data["lastname"]) == "") {
            $data["lastname"] = " unknown";
        }
        if (trim((string) $data["firstname"]) == "") {
            $data["firstname"] = $data["lastname"];
        }

        if ($countryCode && $shippingInfo["region_id"]) {
            $regionModel = $this->regionFactory->create()
                ->loadByCode($shippingInfo["region_id"], $countryCode);
            if ($regionModel->getId()) {
                $data["region_id"] = $regionModel->getId();
                $data["region"] = $regionModel->getData("name");
            } else {
                $data["region_id"] = null;
            }
        }

        if ($create) {
            $shippingAddress = $this->addressInterfaceFactory->create();
        } else {
            $shippingAddress = $this->orderAddressFactory->create();
        }

        $data = $this->fixAddressData($data);

        $shippingAddress->setStoreId($storeId);
        $shippingAddress->setData($data);

        return $shippingAddress;
    }

    /**
     * Get Order Billing Address
     *
     * @param array $billingInfo
     * @param array $shippingInfo
     * @param int $storeId
     * @param int $create
     * @return mixed
     */
    public function getBillingAddress(
        array $billingInfo,
        array $shippingInfo,
        int $storeId,
        int $create = 1
    ): mixed {
        if ((!$billingInfo["country_id"]) || ($billingInfo["country_id"] == "  ")) {
            $countryCode = $shippingInfo["country_id"];
            if ($countryCode == "PR") {
                $countryCode = "US";
                $billingInfo["region_id"] = "PR";
            }
        } else {
            $countryCode = $billingInfo["country_id"];
            if ($countryCode == "PR") {
                $countryCode = "US";
                $billingInfo["region_id"] = "PR";
            }
        }

        if (!$billingInfo["firstname"]) {
            $firstName = $shippingInfo["firstname"];
        } else {
            $firstName = $billingInfo["firstname"];
        }

        if (!$billingInfo["lastname"]) {
            $lastName = $shippingInfo["lastname"];
        } else {
            $lastName = $billingInfo["lastname"];
        }

        if (!$billingInfo["company"]) {
            $companyName = $shippingInfo["company"];
        } else {
            $companyName = $billingInfo["company"];
        }

        $billingStreetAddr = $billingInfo["street"];
        $shipingStreetAddr = $shippingInfo["street"];
        if (!$billingStreetAddr) {
            $address = $shipingStreetAddr;
        } else {
            $address = $billingStreetAddr;
        }

        if (!$billingInfo["city"]) {
            $city = $shippingInfo["city"];
        } else {
            $city = $billingInfo["city"];
        }

        if (!$billingInfo["region_id"]) {
            $region = $shippingInfo["region_id"];
        } else {
            $region = $billingInfo["region_id"];
        }

        if (!$billingInfo["region"]) {
            $regionDescription = $shippingInfo["region"];
        } else {
            $regionDescription = $billingInfo["region"];
        }

        if (!$billingInfo["postcode"]) {
            $postalCode = $shippingInfo["postcode"];
        } else {
            $postalCode = $billingInfo["postcode"];
        }

        if (!$billingInfo["telephone"]) {
            $phoneNumberDay = $shippingInfo["telephone"];
        } else {
            $phoneNumberDay = $billingInfo["telephone"];
        }

        if (trim((string) $phoneNumberDay) == "") {
            $phoneNumberDay = 0000;
        }
        if (trim((string) $lastName) == "") {
            $lastName = " unknown";
        }
        if (trim((string) $firstName) == "") {
            $firstName = $lastName;
        }

        $data = [
            "firstname" => $firstName,
            "lastname" => $lastName,
            "company" => $companyName,
            "street" => $address,
            "city" => $city,
            "region_id" => $region,
            "country_id" => $countryCode,
            "region" => $regionDescription,
            "postcode" => $postalCode,
            "telephone" => $phoneNumberDay,
        ];

        if ($countryCode && $region) {
            $regionModel = $this->regionFactory->create()
                ->loadByCode($region, $countryCode);
            if ($regionModel->getId()) {
                $data["region_id"] = $regionModel->getId();
                $data["region"] = $regionModel->getData("name");
            } else {
                $data["region_id"] = null;
            }
        }

        if ($create) {
            $billingAddress = $this->addressInterfaceFactory->create();
        } else {
            $billingAddress = $this->orderAddressFactory->create();
        }

        $data = $this->fixAddressData($data);

        $billingAddress->setStoreId($storeId);
        $billingAddress->setData($data);

        return $billingAddress;
    }

    /**
     * Fix Order Address Data
     *
     * @param array $data
     * @return array
     */
    public function fixAddressData(array $data): array
    {
        $regionCode = "";
        if ($data['country_id'] == "HR" && !$data['region_id']) {
            if (trim((string) $data['city']) == 'Rijeka') {
                $regionCode = "HR-08";
            }
        }

        if ($data['country_id'] == "CH" && !$data['region_id']) {
            if (trim((string) $data['city']) == 'Geneva') {
                $regionCode = "GE";
            }
        }

        if ($data['country_id'] == "AT" && !$data['region_id']) {
            $regions = [
                'Wien' => 'WI',
                'Niederösterreich' => 'NO',
                'Oberösterreich' => 'OO',
                'Salzburg' => 'SB',
                'Kärnten' => 'KN',
                'Steiermark' => 'ST',
                'Tirol' => "TI",
                'Burgenland' => "BL",
                'Vorarlberg' => "VB"
            ];

            if (isset($regions[trim((string) $data['region'])])) {
                $regionCode = $regions[trim((string) $data['region'])];
            }

            if (trim((string) $data['city']) == 'Pfarrkirchen') {
                $regionCode = "OO";
            }
        }

        if ($regionCode) {
            $regionModel = $this->regionFactory->create()
                                ->loadByCode($regionCode, $data['country_id']);
            if ($regionModel->getId()) {
                $data["region_id"] = $regionModel->getId();
                $data["region"] = $regionModel->getData("name");
            }
        }

        return $data;
    }

    /**
     * Get Order Item
     *
     * @param array $item
     * @param int $storeId
     * @return \Magento\Sales\Model\Order\Item
     */
    public function getOrderItem(
        array $item,
        int $storeId
    ): \Magento\Sales\Model\Order\Item {
        $orderItem = $this->orderItemFactory->create();
        $sku = $item["sku"];
        $productId = $this->productFactory->create()
            ->setStoreId($storeId)
            ->getIdBySku($sku);
        if ($productId) {
            $orderItem->setData("product_id", $productId);
        } else {
            $this->log("WARN: sku " . $sku . " not found");
        }
        $orderItem->setData("product_type", "simple");
        $orderItem->setData("store_id", $storeId);
        $orderItem->setData("is_virtual", 0);
        $orderItem->setData("base_weee_tax_applied_amount", 0);
        $orderItem->setData("base_weee_tax_applied_row_amnt", 0);
        $orderItem->setData("base_weee_tax_applied_row_amount", 0);
        $orderItem->setData("weee_tax_applied_amount", 0);
        $orderItem->setData("weee_tax_applied_row_amount", 0);
        $orderItem->setData("weee_tax_applied", 0);
        $orderItem->setData("weee_tax_disposition", 0);
        $orderItem->setData("weee_tax_row_disposition", 0);
        $orderItem->setData("base_weee_tax_disposition", 0);
        $orderItem->setData("base_weee_tax_row_disposition", 0);

        $orderItem->setData("site_order_item_id", $item["id"]);

        if ($this->isTaxInclusive) {
            $rowTax = (float) $item["tax_amount"];
            $itemTax = $rowTax / (int) $item["qty"];
            $itemPrice = (float) $item["price"] - $itemTax;
            $priceInclTax = (float) $item["price"];
        } else {
            $rowTax = (float) $item["tax_amount"];
            $itemTax = $rowTax / (int) $item["qty"];
            $itemPrice = (float) $item["price"];
            $priceInclTax = (float) $item["price"] + $itemTax;
        }

        $rowTotal = $itemPrice * (int) $item["qty"];
        $rowTotalInclTax = $priceInclTax * (int) $item["qty"];
        $originalPrice = $this->originalPriceInclTax ? $priceInclTax : $itemPrice;

        $orderItem->setData("tax_amount", $rowTax);
        $orderItem->setData("base_tax_amount", $rowTax);
        $orderItem->setData('tax_percent', (float) $item["tax_percent"]);
        $orderItem->setData("sku", $sku)
            ->setData("name", $item["name"])
            ->setData("weight", (float) $item["weight"])
            ->setData("qty_ordered", (int) $item["qty"])
            ->setData("price", $itemPrice)
            ->setData("base_price", $itemPrice)
            ->setData("price_incl_tax", $priceInclTax)
            ->setData("base_price_incl_tax", $priceInclTax)
            ->setData("original_price", $originalPrice)
            ->setData("base_original_price", $originalPrice)
            ->setData("row_total", $rowTotal)
            ->setData("base_row_total", $rowTotal)
            ->setData("row_total_incl_tax", $rowTotalInclTax)
            ->setData("base_row_total_incl_tax", $rowTotalInclTax);

        return $orderItem;
    }

    /**
     * Update Order Item
     *
     * @param \Magento\Sales\Model\Order\Item $mageOrderItem
     * @param \Magento\Sales\Model\Order\Item $orderItem
     * @return \Magento\Sales\Model\Order\Item
     */
    public function updateOrderItemCalculation(
        \Magento\Sales\Model\Order\Item $mageOrderItem,
        \Magento\Sales\Model\Order\Item $orderItem
    ): \Magento\Sales\Model\Order\Item {
        $mageOrderItem->setData("product_type", "simple");
        $mageOrderItem->setData("store_id", $orderItem->getData("store_id"));
        $mageOrderItem->setData("is_virtual", 0);
        $mageOrderItem->setData("base_weee_tax_applied_amount", 0);
        $mageOrderItem->setData("base_weee_tax_applied_row_amnt", 0);
        $mageOrderItem->setData("base_weee_tax_applied_row_amount", 0);
        $mageOrderItem->setData("weee_tax_applied_amount", 0);
        $mageOrderItem->setData("weee_tax_applied_row_amount", 0);
        $mageOrderItem->setData("weee_tax_applied", 0);
        $mageOrderItem->setData("weee_tax_disposition", 0);
        $mageOrderItem->setData("weee_tax_row_disposition", 0);
        $mageOrderItem->setData("base_weee_tax_disposition", 0);
        $mageOrderItem->setData("base_weee_tax_row_disposition", 0);
        $mageOrderItem->setData("site_order_item_id", $orderItem->getData("site_order_item_id"));
        $mageOrderItem->setData("tax_amount", $orderItem->getData("tax_amount"));
        $mageOrderItem->setData("base_tax_amount", $orderItem->getData("base_tax_amount"));
        $mageOrderItem->setData('tax_percent', $orderItem->getData('tax_percent'));
        $mageOrderItem->setData("sku", $orderItem->getData("sku"))
            ->setData("name", $orderItem->getData("name"))
            ->setData("weight", $orderItem->getData("weight"))
            ->setData("qty_ordered", $orderItem->getData("qty_ordered"))
            ->setData("price", $orderItem->getData("price"))
            ->setData("base_price", $orderItem->getData("base_price"))
            ->setData("price_incl_tax", $orderItem->getData("price_incl_tax"))
            ->setData("base_price_incl_tax", $orderItem->getData("base_price_incl_tax"))
            ->setData("original_price", $orderItem->getData("original_price"))
            ->setData("base_original_price", $orderItem->getData("base_original_price"))
            ->setData("row_total", $orderItem->getData("row_total"))
            ->setData("base_row_total", $orderItem->getData("base_row_total"))
            ->setData("row_total_incl_tax", $orderItem->getData("row_total_incl_tax"))
            ->setData("base_row_total_incl_tax", $orderItem->getData("base_row_total_incl_tax"));

        return $mageOrderItem;
    }

    /**
     * Check if sku is existing
     *
     * @param array $ioOrderItems
     * @param int $storeId
     * @return int
     */
    public function isSkuInOrderMissing(array $ioOrderItems, int $storeId): int
    {
        foreach ($ioOrderItems as $orderItem) {
            $sku = $orderItem["sku"];
            $product = $this->skuHelper->loadBySku($sku, $storeId);
            if (!$product) {
                $this->log("WARN: sku " . $sku . " not found");
                return 1;
            }
        }

        return 0;
    }

    /**
     * Get Product Bundle Options
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return array
     */
    public function getBundleOptions(
        \Magento\Catalog\Api\Data\ProductInterface $product
    ): array {
        $bundleOptions = [];
        if (!$product || !$product->getId()) {
            return $bundleOptions;
        }
        $selectionCollection = $product->getTypeInstance()
            ->getSelectionsCollection(
                $product->getTypeInstance()->getOptionsIds($product),
                $product
            );
        foreach ($selectionCollection as $selection) {
            $bundleOptions[$selection->getOptionId()][] = $selection->getSelectionId();
        }

        return $bundleOptions;
    }

    /**
     * Get Payment Info
     *
     * @param array $paymentInfo
     * @return \Magento\Sales\Model\Order\Payment
     */
    public function getPaymentInfo(array $paymentInfo): \Magento\Sales\Model\Order\Payment
    {
        $magePaymentMethods = $this->getMagentoPaymentMethods();
        $defaultPaymentMethod = key($magePaymentMethods);
        $ioPaymentMethod = $paymentInfo["payment_method"];
        if (!$ioPaymentMethod || !isset($magePaymentMethods[$ioPaymentMethod])) {
            $ioPaymentMethod = $defaultPaymentMethod;
        }

        $orderPayment = $this->paymentFactory->create();
        $orderPayment->setData("method", $ioPaymentMethod)
            ->setData("cc_last4", $paymentInfo["cc_last4"]);

        return $orderPayment;
    }

    /**
     * Get Shipping Methods
     *
     * @return array
     */
    public function getMagentoShippingMethods(): array
    {
        $shipMethods = [];
        $activeCarriers = $this->shippingConfig->getAllCarriers();
        foreach ($activeCarriers as $carrierCode => $carrierModel) {
            $carrierTitle = $this->scopeConfig->getValue(
                'carriers/' . $carrierCode . '/title'
            );
            if ($carrierMethods = $carrierModel->getAllowedMethods()) {
                foreach ($carrierMethods as $methodCode => $methodLabel) {
                    if (is_array($methodLabel)) {
                        foreach ($methodLabel as $methodLabelKey => $methodLabelValue) {
                            $shipMethods[$methodLabelKey] = $methodLabelValue;
                        }
                    } else {
                        $shipMethod = $carrierCode . "_" . $methodCode;
                        $shipMethodTitle = $carrierTitle . " - " . $methodLabel;
                        $shipMethods[$shipMethod] = $shipMethodTitle;
                    }
                }
            }
        }

        return $shipMethods;
    }

    /**
     * Get Payment Methods
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
     * Create Invoice
     *
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    public function createInvoice(\Magento\Sales\Model\Order $order): void
    {
        if ($order->canInvoice()) {
            try {
                $invoice = $this->invoiceManagementInterface->prepareInvoice($order);
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                $invoice->register();

                $transactionSave = $this->transaction->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transactionSave->save();

                $order->setData("base_total_invoiced", $order->getData("base_grand_total"));
                $order->setData("total_invoiced", $order->getData("grand_total"));
                $order->setData("base_total_paid", $order->getData("base_grand_total"));
                $order->setData("total_paid", $order->getData("grand_total"));
                $order->save();

                $payment = $order->getPayment();
                if ($payment->getId()) {
                    $payment->setData("base_shipping_amount", $order->getData("base_shipping_amount"));
                    $payment->setData("shipping_amount", $order->getData("shipping_amount"));
                    $payment->setData("base_amount_ordered", $order->getData("base_grand_total"));
                    $payment->setData("amount_ordered", $order->getData("grand_total"));
                    $payment->setData("base_amount_paid", $order->getData("base_grand_total"));
                    $payment->setData("amount_paid", $order->getData("grand_total"));
                    $payment->save();
                }

                $invoice->setData("base_grand_total", $order->getData("base_grand_total"));
                $invoice->setData("grand_total", $order->getData("grand_total"));
                $invoice->setData("base_discount_amount", $order->getData("base_discount_amount"));
                $invoice->setData("discount_amount", $order->getData("discount_amount"));
                $invoice->save();
            } catch (\Exception $e) {
                $errorMes = "Error while create invoice " . $e->getMessage();
                throw new WebapiException(__($errorMes), 0, 400);
            }
        } else {
            $message = 'Can not create Invoice for Order <'. $order->getIncrementId() . '>';
            $this->results["response"]["data"]["error"][] = $message;
            $this->log($message);
        }
    }

    /**
     * Create Shipment
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $ioOrderInfo
     * @param array $shipmentInfo
     * @return bool
     */
    public function createShipment(
        \Magento\Sales\Model\Order $order,
        array $ioOrderInfo,
        array $shipmentInfo
    ): bool {
        if ($order->hasShipments()) {
            $message = "Skip order <" . $order->getIncrementId() . "> for already has shipment";
            $this->results["response"]["data"]["success"][] = $message;
            $this->log($message);
            return false;
        }

        if ($ioOrderInfo["shipping_status"] != "shipped") {
            $message = "Order <" . $order->getIncrementId() . "> is not shipped on IO";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log($message);
            return false;
        }

        // create shipment
        foreach ($shipmentInfo as $_shipmentInfo) {
            try {
                // shipping date
                if (!$_shipmentInfo["shipping_date"]) {
                    continue;
                }
                // item info
                if (!isset($_shipmentInfo["item_info"]) || !count($_shipmentInfo["item_info"]) ||
                    !isset($_shipmentInfo["item_info"][0])) {
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
                if (!isset($_shipmentInfo["track_info"]) || !count($_shipmentInfo["track_info"]) ||
                    !isset($_shipmentInfo["track_info"][0])) {
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

                $carrierCode = $trackInfo["carrier_code"];
                $className = $trackInfo["title"];
                $shippingDate = $_shipmentInfo["shipping_date"];

                $trackingDetail = [
                    "carrier_code" => $carrierCode,
                    "title" => $className,
                    "number" => $trackingNumber,
                    "created_at" => $shippingDate
                ];

                $convertOrder = $this->convertOrder;
                $shipment = $convertOrder->toShipment($order);
                $shipment->setCreatedAt($shippingDate);

                foreach ($order->getAllItems() as $orderItem) {
                    if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                        continue;
                    }
                    foreach ($_shipmentInfo["item_info"] as $itemInfo) {
                        if ($itemInfo["sku"] == $orderItem->getSku()) {
                            $qtyShipped = (int) $itemInfo["qty"];
                            $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)
                                ->setQty($qtyShipped);
                            $shipment->addItem($shipmentItem);
                        }
                    }
                }

                $track = $this->shipmentTrackFactory->create()
                    ->addData($trackingDetail);
                $shipment->addTrack($track);
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                $shipment->save();
                $shipment->getOrder()->save();
                $message = "Shipment '" . $shipment->getIncrementId() . "' imported for order <" .
                    $order->getIncrementId() . ">";
                $this->results["response"]["data"]["success"][] = $message;
                $this->log($message);
                return true;
            } catch (\Exception $e) {
                throw new WebapiException(__("create shipment " . $e->getMessage()), 0, 400);
            }
        }
        return false;
    }

    /**
     * Create Credit Memo
     *
     * @param string $orderIncrementID
     * @param array $refundInfo
     * @return bool
     */
    public function createCreditMemo(string $orderIncrementID, array $refundInfo): mixed
    {
        $orderObject = $this->orderFactory->create()
            ->loadByIncrementId($orderIncrementID);
        if ($orderObject && !$orderObject->hasInvoices()) {
            $orderObject->setData("status", "closed");
            $orderObject->setData("state", "closed");
            $orderObject->save();
            $message = "Order <" . $orderIncrementID . "> set closed status success";
            $this->results["response"]["data"]["success"][] = $message;
            $this->log($message);
            return false;
        }
        if ($orderObject && !$orderObject->hasCreditmemos()) {
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
                    foreach ($orderObject->getAllItems() as $orderItem) {
                        if ($orderItem->getSku() == $sku) {
                            $infoItems[$orderItem->getId()] = $qtyRefund;
                        }
                    }
                }
                if (!count($infoItems)) {
                    $message = "WARN create credit memo for order <" .
                        $orderObject->getIncrementId() . "> items ordered do not match";
                    $this->results["response"]["data"]["error"][] = $message;
                    $this->log($message);
                    return false;
                }
                $creditMemoData = [
                    'qtys' => $infoItems
                ];
                try {
                    $creditMemo = $this->creditMemoFactory->createByOrder(
                        $orderObject,
                        $creditMemoData
                    );
                    $creditMemo->save();
                    $shippingRefundedTotal += (float) $creditMemo->getData("base_shipping_amount");
                    $shippingTaxRefundedTotal += (float) $creditMemo->getData("shipping_tax_amount");
                    $taxRefundedTotal += (float) $creditMemo->getData("tax_amount");
                    $subtotalRefundedTotal += (float) $creditMemo->getData("subtotal");
                    $totalRefundedTotal += (float) $creditMemo->getData("base_grand_total");

                    $message = "Credit Memo '" . $creditMemo->getIncrementId() .
                        "' imported for order <" . $orderIncrementID . ">";
                    $this->results["response"]["data"]["success"][] = $message;
                    $this->log($message);
                } catch (\Exception $e) {
                    $message = "ERROR create credit memo " . $e->getMessage();
                    $this->results["response"]["data"]["error"][] = $message;
                    $this->log($message);
                    throw new WebapiException(__($message), 0, 400);
                }
            }
            // reset order after save credit memo
            $orderObject->setData("shipping_refunded", $shippingRefundedTotal);
            $orderObject->setData("base_shipping_refunded", $shippingRefundedTotal);
            $orderObject->setData("shipping_tax_refunded", $shippingTaxRefundedTotal);
            $orderObject->setData("tax_refunded", $taxRefundedTotal);
            $orderObject->setData("base_tax_refunded", $taxRefundedTotal);
            $orderObject->setData("subtotal_refunded", $subtotalRefundedTotal);
            $orderObject->setData("base_subtotal_refunded", $subtotalRefundedTotal);
            $orderObject->setData("total_refunded", $totalRefundedTotal);
            $orderObject->setData("base_total_refunded", $totalRefundedTotal);
            $orderObject->save();
            return true;
        } else {
            $message = "Skip order <" . $orderObject->getIncrementId() . "> has been refunded";
            $this->results["response"]["data"]["success"][] = $message;
            $this->log($message);
        }
        return false;
    }

    /**
     * Cancel Order by ID
     *
     * @param int $orderId
     * @return array
     */
    public function cancelById(int $orderId): array
    {
        $this->cancelOrder($orderId, "id");
        $this->cleanResponseMessages();
        return $this->results;
    }

    /**
     * Cancel Order increment ID
     *
     * @param string $incrementId
     * @return array
     */
    public function cancelByIncrementId(string $incrementId): array
    {
        $this->cancelOrder($incrementId);
        $this->cleanResponseMessages();
        return $this->results;
    }

    /**
     * Cancel Order
     *
     * @param int|string $id
     * @param string $typeId
     * @return bool
     */
    public function cancelOrder(int|string $id, string $typeId = 'incrementId'): bool
    {
        $typeId === "id"
        ? $order = $this->orderFactory->create()->load($id)
        : $order = $this->orderFactory->create()->loadByIncrementId($id);
        if (!$order || !$order->getId()) {
            $message = "Cannot load order <{$id}>";
            $this->results["response"]["data"]["error"][] = $message;
            $this->log($message);
            return false;
        }
        if ($order->getStatus() == "canceled") {
            $message = "Order <{$id}> has already been canceled";
            $this->results["response"]["data"]["success"][] = $message;
            $this->log($message);
            return true;
        }
        try {
            $order->cancel();
            $order->setData("status", "canceled");
            $order->setData("state", "canceled");
            $order->save();
            $message = "Order <{$id}> has been successfully canceled";
            $this->results["response"]["data"]["success"][] = $message;
            $this->log($message);
            return true;
        } catch (\Exception $e) {
            $message = "ERROR cancel order <{$id}>: " . $e->getMessage();
            $this->results["response"]["data"]["error"][] = $message;
            $this->log($message);
            $this->cleanResponseMessages();
            throw new WebapiException(__($message), 0, 400);
        }
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
