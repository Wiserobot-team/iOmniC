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

use Wiserobot\Io\Model\IoOrderFactory;
use Wiserobot\Io\Helper\Sku as SkuHelper;

class OrderImport implements \Wiserobot\Io\Api\OrderImportInterface
{
    private $logFile = "wiserobotio_order_import.log";
    private $showLog = false;
    public $results  = [];
    public $scopeConfig;
    public $storeManager;
    public $filesystem;
    public $customerFactory;
    public $customerRepository;
    public $productFactory;
    public $shippingRate;
    public $cartManagementInterface;
    public $cartRepositoryInterface;
    public $orderItemFactory;
    public $orderFactory;
    public $regionFactory;
    public $paymentFactory;
    public $addressInterfaceFactory;
    public $orderAddressFactory;
    public $invoiceManagementInterface;
    public $transaction;
    public $convertOrder;
    public $shipmentTrackFactory;
    public $creditmemoFactory;
    public $eventManager;
    public $productRepository;
    public $shippingConfig;
    public $paymentConfig;
    public $ioOrderFactory;
    public $skuHelper;

    public function __construct(
        ScopeConfigInterface               $scopeConfig,
        StoreManagerInterface              $storeManager,
        Filesystem                         $filesystem,
        CustomerFactory                    $customerFactory,
        CustomerRepositoryInterface        $customerRepository,
        ProductFactory                     $productFactory,
        AddressRate                        $shippingRate,
        CartManagementInterface            $cartManagementInterface,
        CartRepositoryInterface            $cartRepositoryInterface,
        ItemFactory                        $orderItemFactory,
        OrderFactory                       $orderFactory,
        RegionFactory                      $regionFactory,
        PaymentFactory                     $paymentFactory,
        AddressInterfaceFactory            $addressInterfaceFactory,
        AddressFactory                     $orderAddressFactory,
        InvoiceManagementInterface         $invoiceManagementInterface,
        Transaction                        $transaction,
        ConvertOrder                       $convertOrder,
        ShipmentTrackFactory               $shipmentTrackFactory,
        CreditmemoFactory                  $creditmemoFactory,
        EventManager                       $eventManager,
        ProductRepositoryInterface         $productRepository,
        ShippingConfig                     $shippingConfig,
        PaymentConfig                      $paymentConfig,
        IoOrderFactory                     $ioOrderFactory,
        SkuHelper                          $skuHelper
    ) {
        $this->scopeConfig                 = $scopeConfig;
        $this->storeManager                = $storeManager;
        $this->filesystem                  = $filesystem;
        $this->customerFactory             = $customerFactory;
        $this->customerRepository          = $customerRepository;
        $this->productFactory              = $productFactory;
        $this->shippingRate                = $shippingRate;
        $this->cartManagementInterface     = $cartManagementInterface;
        $this->cartRepositoryInterface     = $cartRepositoryInterface;
        $this->orderItemFactory            = $orderItemFactory;
        $this->orderFactory                = $orderFactory;
        $this->regionFactory               = $regionFactory;
        $this->paymentFactory              = $paymentFactory;
        $this->addressInterfaceFactory     = $addressInterfaceFactory;
        $this->orderAddressFactory         = $orderAddressFactory;
        $this->invoiceManagementInterface  = $invoiceManagementInterface;
        $this->transaction                 = $transaction;
        $this->convertOrder                = $convertOrder;
        $this->shipmentTrackFactory        = $shipmentTrackFactory;
        $this->creditmemoFactory           = $creditmemoFactory;
        $this->eventManager                = $eventManager;
        $this->productRepository           = $productRepository;
        $this->shippingConfig              = $shippingConfig;
        $this->paymentConfig               = $paymentConfig;
        $this->ioOrderFactory              = $ioOrderFactory;
        $this->skuHelper                   = $skuHelper;
    }

    public function import($store, $order_info, $payment_info, $shipping_info, $billing_info, $item_info, $status_histories = [], $shipment_info = [], $refund_info = [])
    {
        // response messages
        $this->results["response"]["data"]["success"] = [];
        $this->results["response"]["data"]["error"]   = [];
        $this->results["response"]["item"]["success"] = [];
        $this->results["response"]["item"]["error"]   = [];

        // store info
        if (!$store) {
            $this->results["response"]["data"]["error"][] = "Field: 'store' is a required field";
            $this->log("ERROR: Field: 'store' is a required field");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        try {
            $storeInfo = $this->storeManager->getStore($store);
        } catch (\Exception $e) {
            $this->results["response"]["data"]["error"][] = "Requested 'store' " . $store . " doesn't exist";
            $this->log("ERROR: Requested 'store' " . $store . " doesn't exist");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }

        // order info
        if (!$order_info) {
            $this->results["response"]["data"]["error"][] = "Field: 'order_info' is a required field";
            $this->log("ERROR: Field: 'order_info' is a required field");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        if (!isset($order_info["io_order_id"]) || !$order_info["io_order_id"]) {
            $this->results["response"]["data"]["error"][] = "Field: 'order_info' - 'io_order_id' data is a required";
            $this->log("ERROR: Field: 'order_info' - 'io_order_id' data is a required");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        if (!isset($order_info["ca_order_id"]) || !$order_info["ca_order_id"]) {
            $this->results["response"]["data"]["error"][] = "Field: 'order_info' - 'ca_order_id' data is a required";
            $this->log("ERROR: Field: 'order_info' - 'ca_order_id' data is a required");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        if (!isset($order_info["order_time_gmt"])      || !$order_info["order_time_gmt"]
            || !isset($order_info["email"])            || !$order_info["email"]
            || !isset($order_info["item_sale_source"]) || !$order_info["item_sale_source"]
            || !isset($order_info["checkout_status"])  || !$order_info["checkout_status"]
            || !isset($order_info["shipping_status"])  || !$order_info["shipping_status"]
            || !isset($order_info["refund_status"])    || !$order_info["refund_status"]
            || !isset($order_info["grand_total"])      || !isset($order_info["tax_amount"])
            || !isset($order_info["shipping_amount"])  || !isset($order_info["shipping_tax_amount"])
            || !isset($order_info["discount_amount"])) {
            $this->results["response"]["data"]["error"][] = "Field: 'order_info' - {'order_time_gmt', 'email', 'item_sale_source', 'grand_total', 'tax_amount', 'shipping_amount', 'shipping_tax_amount', 'discount_amount', 'checkout_status', 'shipping_status', 'refund_status'} data fields are required";
            $this->log("ERROR: Field: 'order_info' - {'order_time_gmt', 'email', 'item_sale_source', 'grand_total', 'tax_amount', 'shipping_amount', 'shipping_tax_amount', 'discount_amount', 'checkout_status', 'shipping_status', 'refund_status'} data fields are required");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }

        // payment info
        if (!$payment_info) {
            $this->results["response"]["data"]["error"][] = "Field: 'payment_info' is a required field";
            $this->log("ERROR: Field: 'payment_info' is a required field");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        if (!isset($payment_info["payment_method"]) || !$payment_info["payment_method"] || !isset($payment_info["cc_last4"])) {
            $this->results["response"]["data"]["error"][] = "Field: 'payment_info' - {'payment_method', 'cc_last4'} data fields are required";
            $this->log("ERROR: Field: 'payment_info' - {'payment_method', 'cc_last4'} data fields are required");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }

        // shipping info
        if (!$shipping_info) {
            $this->results["response"]["data"]["error"][] = "Field: 'shipping_info' is a required field";
            $this->log("ERROR: Field: 'shipping_info' is a required field");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        if (!isset($shipping_info["firstname"])     || !isset($shipping_info["lastname"]) || !isset($shipping_info["company"])
            || !isset($shipping_info["street"])     || !isset($shipping_info["city"])     || !isset($shipping_info["region_id"])
            || !isset($shipping_info["country_id"]) || !isset($shipping_info["region"])   || !isset($shipping_info["postcode"])
            || !isset($shipping_info["telephone"])  || !isset($shipping_info["shipping_method"])) {
            $this->results["response"]["data"]["error"][] = "Field: 'shipping_info' - {'firstname', 'lastname', 'company', 'street', 'city', 'region_id', 'country_id', 'region', 'postcode', 'telephone', 'shipping_method'} data fields are required";
            $this->log("ERROR: Field: 'shipping_info' - {'firstname', 'lastname', 'company', 'street', 'city', 'region_id', 'country_id', 'region', 'postcode', 'telephone', 'shipping_method'} data fields are required");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }

        // billing info
        if (!$billing_info) {
            $this->results["response"]["data"]["error"][] = "Field: 'billing_info' is a required field";
            $this->log("ERROR: Field: 'billing_info' is a required field");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        if (!isset($billing_info["firstname"])     || !isset($billing_info["lastname"]) || !isset($billing_info["company"])
            || !isset($billing_info["street"])     || !isset($billing_info["city"])     || !isset($billing_info["region_id"])
            || !isset($billing_info["country_id"]) || !isset($billing_info["region"])   || !isset($billing_info["postcode"])
            || !isset($billing_info["telephone"])) {
            $this->results["response"]["data"]["error"][] = "Field: 'billing_info' - {'firstname', 'lastname', 'company', 'street', 'city', 'region_id', 'country_id', 'region', 'postcode', 'telephone'} data fields are required";
            $this->log("ERROR: Field: 'billing_info' - {'firstname', 'lastname', 'company', 'street', 'city', 'region_id', 'country_id', 'region', 'postcode', 'telephone'} data fields are required");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }

        // item info
        if (!$item_info || !count($item_info)) {
            $this->results["response"]["item"]["error"][] = "Field: 'item_info' is a required field";
            $this->log("ERROR: Field: 'item_info' is a required field");
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
        }
        foreach ($item_info as $item) {
            if (!isset($item["id"])        || !isset($item["sku"])         || !$item["sku"]
                || !isset($item["name"])   || !$item["name"]               || !isset($item["price"])
                || !isset($item["qty"])    || !isset($item["tax_percent"]) || !isset($item["tax_amount"])
                || !isset($item["weight"]) || !isset($item["buyer_user_id"])) {
                $this->results["response"]["item"]["error"][] = "Field: 'item_info' - {'id', 'sku', 'name', 'price', 'qty', 'tax_percent', 'tax_amount', 'weight', 'buyer_user_id'} data fields are required";
                $this->log("ERROR: Field: 'item_info' - {'id', 'sku', 'name', 'price', 'qty', 'tax_percent', 'tax_amount', 'weight', 'buyer_user_id'} data fields are required");
                $this->cleanResponseMessages();
                throw new \Magento\Framework\Webapi\Exception(__("data request error"), 0, 400, $this->results["response"]);
            }
        }

        // import io order
        try {
            $this->importIoOrder($order_info, $payment_info, $shipping_info, $billing_info, $status_histories, $shipment_info, $refund_info, $item_info, $storeInfo);
            $this->cleanResponseMessages();
            return $this->results;
        } catch (\Exception $e) {
            throw new \Magento\Framework\Webapi\Exception(__("order import error"), 0, 400, $this->results["response"]);
        }
    }

    public function importIoOrder($orderInfo, $paymentInfo, $shippingInfo, $billingInfo, $statusHistories, $shipmentInfo, $refundInfo, $itemInfo, $store)
    {
        $storeId        = $store->getId();
        $ioOrderId      = $orderInfo["io_order_id"];
        $caOrderId      = $orderInfo["ca_order_id"];
        $buyerUserID    = "";
        $itemSaleSource = $orderInfo["item_sale_source"];

        try {
            // order items
            $orderItemsWeightTotal = 0;
            $orderItemsQtyTotal    = 0;
            $orderSubtotal         = 0;

            if (!is_array($itemInfo)) {
                $itemInfo = array($itemInfo);
            }

            $ioOrderItems = [];
            foreach ($itemInfo as $orderItem) {
                $sku         = $orderItem["sku"];
                $buyerUserID = $orderItem["buyer_user_id"];
                if (!isset($ioOrderItems[$sku])) {
                    $ioOrderItems[$sku]  = $orderItem;
                }
            }

            // check if an order already exists
            $oldIoOrder = $this->ioOrderFactory->create();
            $oldIoOrder->load($ioOrderId, "io_order_id");

            $isOldOrder      = false;
            $skuIsMissing    = false;
            $refundStatus    = $orderInfo["refund_status"];
            if ($oldIoOrder->getId()) {
                $oldOrderIId = $oldIoOrder->getData("order_increment_id");
                $oldOrder    = $this->orderFactory->create()->loadByIncrementId($oldOrderIId);
                if ($oldOrder && $oldOrder->getId()) {
                    if ($oldOrder->getStatus() == "closed") {
                        $this->results["response"]["data"]["success"][] = "skip order " . $ioOrderId . " update to <" . $oldOrderIId . "> for order has been closed";
                        $this->log("Skip order " . $ioOrderId . " update to <" . $oldOrderIId . "> for order has been closed");
                        return;
                    }
                    if ($oldOrder->hasShipments()) {
                        $this->results["response"]["data"]["success"][] = "skip order " . $ioOrderId . " update to <" . $oldOrderIId . "> for order has been shipped";
                        $this->log("Skip order " . $ioOrderId . " update to <" . $oldOrderIId . "> for order has been shipped");
                        // create credit memo for order
                        if ($refundStatus && $refundStatus != "unrefunded" && count($refundInfo)) {
                            $this->createCreditMemo($oldOrderIId, $refundInfo);
                            $orderObject = $this->orderFactory->create()->loadByIncrementId($oldOrder->getIncrementId());
                            if ($orderObject->hasShipments()) {
                                if ($orderObject->getStatus() != "closed") {
                                    if (in_array($orderObject->getStatus(), ["complete"])) {
                                        $orderObject->setData("status", "closed");
                                        $orderObject->setData("state", "closed");
                                        $orderObject->save();
                                    }
                                }
                            }
                        }
                        return;
                    } else {
                        // check and create credit memo for order
                        if ($refundStatus && $refundStatus != "unrefunded" && count($refundInfo)) {
                            $this->createCreditMemo($oldOrderIId, $refundInfo);
                            $orderObject = $this->orderFactory->create()->loadByIncrementId($oldOrder->getIncrementId());
                            if (!$orderObject->hasShipments()) {
                                if ($orderObject->getStatus() != "closed") {
                                    if (in_array($orderObject->getStatus(), ["processing"])) {
                                        $orderObject->setData("status", "closed");
                                        $orderObject->setData("state", "closed");
                                        $orderObject->save();
                                    }
                                }
                            }
                            return;
                        }
                        if ($oldOrder->getStatus() == "canceled") {
                            $this->results["response"]["data"]["success"][] = "skip order " . $ioOrderId . " update to <" . $oldOrderIId . "> for order has been canceled";
                            $this->log("Skip order " . $ioOrderId . " update to <" . $oldOrderIId . "> for order has been canceled");
                            return;
                        }
                        if ($orderInfo["checkout_status"] != "canceled") {
                            $this->results["response"]["data"]["success"][] = "order " . $ioOrderId . " updated into <" . $oldOrderIId . ">";
                            $this->log("Order " . $ioOrderId . " updated into <" . $oldOrderIId . ">");
                            return;
                        }
                    }
                    $isOldOrder = true;
                    $cart       = $oldOrder;
                } else {
                    $this->results["response"]["data"]["error"][] = "warn cannot load order <" . $oldOrderIId . ">";
                    $this->log("WARN cannot load order <" . $oldOrderIId . ">");
                    return;
                }
                // create shipment for order
                if ($orderInfo["checkout_status"] != "canceled" && $orderInfo["shipping_status"] == "shipped") {
                    $this->createShipment($oldOrder, $orderInfo, $shipmentInfo);
                    return;
                }
            } else {
                $skuIsMissing = $this->isSkuInOrderMissing($ioOrderItems, $storeId);
                if ($skuIsMissing) {
                    // create order object if the SKU not found, bypass checkout flow
                    $cart   = $this->orderFactory->create();
                } else {
                    // create cart object if no SKU missing, like normally checkout
                    $cartId = $this->cartManagementInterface->createEmptyCart();
                    $cart   = $this->cartRepositoryInterface->get($cartId);
                }
                $cart->setStoreId($storeId);
            }

            // shipping, billing address info
            if (!$isOldOrder) {
                if ($skuIsMissing) {
                    $shippingAddress = $this->getShippingAddress($shippingInfo, $storeId, 0);
                    $billingAddress  = $this->getBillingAddress($billingInfo, $shippingInfo, $storeId, 0);
                } else {
                    $shippingAddress = $this->getShippingAddress($shippingInfo, $storeId);
                    $billingAddress  = $this->getBillingAddress($billingInfo, $shippingInfo, $storeId);
                }
            } else {
                $shippingAddress = $this->getShippingAddress($shippingInfo, $storeId, 0);
                $billingAddress  = $this->getBillingAddress($billingInfo, $shippingInfo, $storeId, 0);
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
                        $this->customerRepositoryInterface->save($customer);
                    } catch (\Exception $e) {
                        $this->results["response"]["data"]["error"][] = "order " . $ioOrderId . ": update customer " . $e->getMessage();
                        $message = "ERROR " . $ioOrderId . ": UPDATE CUSTOMER " . $e->getMessage();
                        $this->log($message);
                        $this->cleanResponseMessages();
                        throw new \Magento\Framework\Webapi\Exception(__($e->getMessage()), 0, 400);
                    }
                }
                if (!$isOldOrder) {
                    if (!$skuIsMissing) {
                        $cart->assignCustomer($customer);
                    }
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
                        $this->results["response"]["item"]["error"][] = "order " . $ioOrderId . " has invalid item";
                        $this->log("WARN order " . $ioOrderId . " has invalid item");
                        return false;
                    }

                    if ($skuIsMissing) {
                        $cart->addItem($webOrderItem);
                    } else {
                        $product = $this->productRepository->getById($webOrderItem['product_id'], false, $store->getId());
                        // $product->setPrice($webOrderItem['price']);
                        // $product->setSpecialPrice($webOrderItem['price']);

                        /*if (!intval($webOrderItem['price'])) {
                            $additionalOptions = [];
                            $additionalOptions['no_price'] = [
                                'label' => 'Price',
                                'value' => 'No',
                            ];

                            $product->addCustomOption('additional_options', serialize($additionalOptions));
                        }*/

                        try {
                            $item = $cart->addProduct($product, intval($webOrderItem['qty_ordered']));
                            if (is_string($item)) {
                                $this->results["response"]["item"]["error"][] = "order " . $ioOrderId . " product '" . $product->getSku() . "': " . $item;
                                $message = "ERROR order " . $ioOrderId . " product '" . $product->getSku() . "': " . $item;
                                $this->log($message);
                                $this->eventManager->dispatch(
                                    'wiserobotio_order_import_error',
                                    [
                                        'order_client_id' => $ioOrderId,
                                        'sku'             => $product->getSku(),
                                        'product_id'      => $product->getId(),
                                        'error'           => $message
                                    ]
                                );
                                return false;
                            }
                            $item->setCustomPrice($webOrderItem['price']);
                            $item->setOriginalCustomPrice($webOrderItem['price']);
                            $item->setIsSuperMode(true);
                        } catch (\Exception $e) {
                            $this->results["response"]["item"]["error"][] = "order " . $ioOrderId . " product '" . $product->getSku() . "': " . $e->getMessage();
                            $message = "ERROR order " . $ioOrderId . " product '" . $product->getSku() . "': " . $e->getMessage();
                            $this->log($message);
                            $this->cleanResponseMessages();
                            $this->eventManager->dispatch(
                                'wiserobotio_order_import_error',
                                [
                                    'order_client_id' => $ioOrderId,
                                    'sku'             => $product->getSku(),
                                    'product_id'      => $product->getId(),
                                    'error'           => $message
                                ]
                            );
                            throw new \Magento\Framework\Webapi\Exception(__($e->getMessage()), 0, 400);
                        }
                    }

                    $orderItemsWeightTotal += (float) $orderItem["weight"];
                    $orderItemsQtyTotal    += (int) $orderItem["qty"];
                    $orderSubtotal         += ((float) $orderItem["qty"] * $webOrderItem->getData('price'));
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
            if (!count($mageShippingMethods)) {
                $mageShippingMethods = $this->getAllMagentoShippingMethods();
            }
            $defaultShippingMethod = key($mageShippingMethods);
            $ioShippingMethod      = $shippingInfo["shipping_method"];
            if (!$ioShippingMethod || !isset($mageShippingMethods[$ioShippingMethod])) {
                $ioShippingMethod  = $defaultShippingMethod;
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
                        "method"   => $paymentInfo->getData("method"),
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
                    $cart     = $this->cartRepositoryInterface->get($cart->getId());
                    $orderID  = $this->cartManagementInterface->placeOrder($cart->getId());
                    $newOrder = $this->orderFactory->create()->load($orderID);
                }
            } else {
                $newOrder = $cart;
            }

            // re-add shipping method if order is created by cart
            if (!$isOldOrder) {
                if (!$skuIsMissing) {
                    if ($ioShippingMethod && $ioShippingDescription) {
                        $newOrder->setData("shipping_method", $ioShippingMethod);
                        $newOrder->setData("shipping_description", $ioShippingDescription);
                    }
                }
            }

            // update order items
            if (!$isOldOrder) {
                if (!$skuIsMissing) {
                    foreach ($ioOrderItems as $orderItem) {
                        $webOrderItem   = $this->getOrderItem($orderItem, $storeId);
                        $mageOrderItems = $newOrder->getItemsCollection();
                        if ($mageOrderItems) {
                            foreach ($mageOrderItems as $mageOrderItem) {
                                $productId = $mageOrderItem->getProductId();
                                if ($productId && $productId == $webOrderItem['product_id']) {
                                    $updateItem = $this->updateOrderItemCalculation($mageOrderItem, $webOrderItem);
                                    $updateItem->save();
                                }
                            }
                        }
                    }
                }
            }

            // order status info
            if ($orderInfo["checkout_status"] == "canceled") {
                $status = "canceled";
                $state  = "canceled";
            } elseif ($orderInfo["checkout_status"] == "completed") {
                $status = "processing";
                $state  = "processing";
            } else {
                $status = "pending";
                $state  = "new";
            }
            $newOrder->setData("status", $status);
            $newOrder->setData("state", $state);

            // shipping info
            $shippingAmount = $orderInfo["shipping_amount"] ? $orderInfo["shipping_amount"] : 0;
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

                $currencyCode = $store->getCurrentCurrencyCode();

                $newOrder->setData("base_shipping_amount", $orderInfo["shipping_amount"]);
                $newOrder->setData("shipping_amount", $orderInfo["shipping_amount"]);
                $newOrder->setData("shipping_tax_amount", $orderInfo["shipping_tax_amount"]);
                $newOrder->setData("base_shipping_tax_amount", $orderInfo["shipping_tax_amount"]);

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
            $newOrder->setData("ca_order_id", $caOrderId);
            $newOrder->setData("buyer_user_id", $buyerUserID);
            $newOrder->setData("io_marketplace", $itemSaleSource);

            // add comment
            if (!$isOldOrder) {
                $statusMessage  = "Marketplace: " . $itemSaleSource . ", ChannelAdvisor Order ID: " . $caOrderId . ", Site Order ID: " . $ioOrderId . ", Buyer User ID: " . $buyerUserID;
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

            // save order
            $savedOrder = $newOrder->save();

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
                    $this->results["response"]["data"]["success"][] = "order " . $ioOrderId . " updated into <" . $savedOrder->getIncrementId() . "> has been canceled";
                    $this->log("Order " . $ioOrderId . " updated into <" . $savedOrder->getIncrementId() . "> has been canceled");
                } else {
                    $this->results["response"]["data"]["success"][] = "order " . $ioOrderId . " updated into <" . $savedOrder->getIncrementId() . ">";
                    $this->log("Order " . $ioOrderId . " updated into <" . $savedOrder->getIncrementId() . ">");
                }
            } else {
                $this->results["response"]["data"]["success"][] = "order " . $ioOrderId . " imported into <" . $savedOrder->getIncrementId() . ">";
                $this->log("Order " . $ioOrderId . " imported into <" . $savedOrder->getIncrementId() . ">");
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
            $this->results["response"]["data"]["error"][] = "order " . $ioOrderId . ": " . $e->getMessage();
            $message = "ERROR " . $ioOrderId . ": " . $e->getMessage();
            $this->log($message);
            $this->cleanResponseMessages();
            throw new \Magento\Framework\Webapi\Exception(__($e->getMessage()), 0, 400);
        }
    }

    public function getShippingAddress($shippingInfo, $storeId, $create = 1)
    {
        $CountryCode = $shippingInfo["country_id"];
        if ($shippingInfo["country_id"] == "PR") {
            $CountryCode = "US";
            $shippingInfo["region_id"] = "PR";
        }
        $data = array(
            "firstname"  => $shippingInfo["firstname"],
            "lastname"   => $shippingInfo["lastname"],
            "company"    => $shippingInfo["company"],
            "street"     => $shippingInfo["street"],
            "city"       => $shippingInfo["city"],
            "region_id"  => $shippingInfo["region_id"],
            "country_id" => $CountryCode,
            "region"     => $shippingInfo["region"],
            "postcode"   => $shippingInfo["postcode"],
            "telephone"  => $shippingInfo["telephone"],
        );

        if (trim((string) $data["telephone"]) == "") {
            $data["telephone"] = 0000;
        }
        if (trim((string) $data["lastname"]) == "") {
            $data["lastname"] = " unknown";
        }
        if (trim((string) $data["firstname"]) == "") {
            $data["firstname"] = $data["lastname"];
        }

        if ($CountryCode && $shippingInfo["region_id"]) {
            $regionModel = $this->regionFactory->create()
                                ->loadByCode($shippingInfo["region_id"], $CountryCode);
            if ($regionModel->getId()) {
                $data["region_id"] = $regionModel->getId();
                $data["region"]    = $regionModel->getData("name");
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

    public function getBillingAddress($billingInfo, $shippingInfo, $storeId, $create = 1)
    {
        if ((!$billingInfo["country_id"]) || ($billingInfo["country_id"] == "  ")) {
            $CountryCode = $shippingInfo["country_id"];
            if ($CountryCode == "PR") {
                $CountryCode = "US";
                $billingInfo["region_id"] = "PR";
            }
        } else {
            $CountryCode = $billingInfo["country_id"];
            if ($CountryCode == "PR") {
                $CountryCode = "US";
                $billingInfo["region_id"] = "PR";
            }
        }

        if (!$billingInfo["firstname"]) {
            $FirstName = $shippingInfo["firstname"];
        } else {
            $FirstName = $billingInfo["firstname"];
        }

        if (!$billingInfo["lastname"]) {
            $LastName = $shippingInfo["lastname"];
        } else {
            $LastName = $billingInfo["lastname"];
        }

        if (!$billingInfo["company"]) {
            $CompanyName = $shippingInfo["company"];
        } else {
            $CompanyName = $billingInfo["company"];
        }

        $billingStreetAddr = $billingInfo["street"];
        $shipingStreetAddr = $shippingInfo["street"];
        if (!$billingStreetAddr) {
            $Address = $shipingStreetAddr;
        } else {
            $Address = $billingStreetAddr;
        }

        if (!$billingInfo["city"]) {
            $City = $shippingInfo["city"];
        } else {
            $City = $billingInfo["city"];
        }

        if (!$billingInfo["region_id"]) {
            $Region = $shippingInfo["region_id"];
        } else {
            $Region = $billingInfo["region_id"];
        }

        if (!$billingInfo["region"]) {
            $RegionDescription = $shippingInfo["region"];
        } else {
            $RegionDescription = $billingInfo["region"];
        }

        if (!$billingInfo["postcode"]) {
            $PostalCode = $shippingInfo["postcode"];
        } else {
            $PostalCode = $billingInfo["postcode"];
        }

        if (!$billingInfo["telephone"]) {
            $PhoneNumberDay = $shippingInfo["telephone"];
        } else {
            $PhoneNumberDay = $billingInfo["telephone"];
        }

        if (trim((string) $PhoneNumberDay) == "") {
            $PhoneNumberDay = 0000;
        }
        if (trim((string) $LastName) == "") {
            $LastName = " unknown";
        }
        if (trim((string) $FirstName) == "") {
            $FirstName = $LastName;
        }

        $data = array(
            "firstname"  => $FirstName,
            "lastname"   => $LastName,
            "company"    => $CompanyName,
            "street"     => $Address,
            "city"       => $City,
            "region_id"  => $Region,
            "country_id" => $CountryCode,
            "region"     => $RegionDescription,
            "postcode"   => $PostalCode,
            "telephone"  => $PhoneNumberDay,
        );

        if ($CountryCode && $Region) {
            $regionModel = $this->regionFactory->create()
                                ->loadByCode($Region, $CountryCode);
            if ($regionModel->getId()) {
                $data["region_id"] = $regionModel->getId();
                $data["region"]    = $regionModel->getData("name");
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

    public function fixAddressData($data)
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
                'Wien'             => 'WI',
                'Niederösterreich' => 'NO',
                'Oberösterreich'   => 'OO',
                'Salzburg'         => 'SB',
                'Kärnten'          => 'KN',
                'Steiermark'       => 'ST',
                'Tirol'            => "TI",
                'Burgenland'       => "BL",
                'Vorarlberg'       => "VB"
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
                $data["region"]    = $regionModel->getData("name");
            }
        }

        return $data;
    }

    public function getOrderItem($item, $storeId)
    {
        $orderItem = $this->orderItemFactory->create();
        $sku       = $item["sku"];
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

        $itemPrice    = (float) $item["price"];
        $rowTax       = (float) $item["tax_amount"];
        $itemTax      = $rowTax / (int) $item["qty"];
        $itemPrice    = (float) $item["price"] - $itemTax;
        $priceInclTax = (float) $item["price"];

        $rowTotal        = $itemPrice * (int) $item["qty"];
        $rowTotalInclTax = $priceInclTax * (int) $item["qty"];

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
            ->setData("original_price", $itemPrice)
            ->setData("base_original_price", $itemPrice)
            ->setData("row_total", $rowTotal)
            ->setData("base_row_total", $rowTotal)
            ->setData("row_total_incl_tax", $rowTotalInclTax)
            ->setData("base_row_total_incl_tax", $rowTotalInclTax);

        return $orderItem;
    }

    public function updateOrderItemCalculation($mageOrderItem, $orderItem)
    {
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
     * check order items to see if any sku is not in Magento, return 1 if the sku is missing and 0 if the sku exists
     *
     * @param array $ioOrderItems
     * @param integer $storeId
     * @return integer
     */
    public function isSkuInOrderMissing($ioOrderItems, $storeId)
    {
        foreach ($ioOrderItems as $orderItem) {
            $sku     = $orderItem["sku"];
            $product = $this->skuHelper->loadBySku($sku, $storeId);
            if (!$product) {
                $this->log("WARN: sku " . $sku . " not found");
                return 1;
            }
        }

        return 0;
    }

    public function getPaymentInfo($paymentInfo)
    {
        $magePaymentMethods   = $this->getMagentoPaymentMethods();
        $defaultPaymentMethod = key($magePaymentMethods);
        $ioPaymentMethod      = $paymentInfo["payment_method"];
        if (!$ioPaymentMethod || !isset($magePaymentMethods[$ioPaymentMethod])) {
            $ioPaymentMethod  = $defaultPaymentMethod;
        }

        $orderPayment = $this->paymentFactory->create();
        $orderPayment->setData("method", $ioPaymentMethod)
                     ->setData("cc_last4", $paymentInfo["cc_last4"]);

        return $orderPayment;
    }

    public function getMagentoShippingMethods()
    {
        $shipMethods    = [];
        $activeCarriers = $this->shippingConfig->getActiveCarriers();
        foreach ($activeCarriers as $carrierCode => $carrierModel) {
            $carrierTitle = $this->scopeConfig->getValue('carriers/' . $carrierCode . '/title');
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

    public function getAllMagentoShippingMethods()
    {
        $shipMethods    = [];
        $activeCarriers = $this->shippingConfig->getAllCarriers();
        foreach ($activeCarriers as $carrierCode => $carrierModel) {
            $carrierTitle = $this->scopeConfig->getValue('carriers/' . $carrierCode . '/title');
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

    public function getMagentoPaymentMethods()
    {
        $paymentMethods = [];
        $activePayments = $this->paymentConfig->getActiveMethods();
        foreach ($activePayments as $paymentCode => $paymentModel) {
            $paymentTitle = $this->scopeConfig->getValue('payment/' . $paymentCode . '/title');
            if (!$paymentTitle) {
                continue;
            }
            $paymentMethods[$paymentCode] = $paymentTitle;
        }

        return $paymentMethods;
    }

    public function createInvoice($order)
    {
        if ($order->canInvoice()) {
            try {
                $invoice = $this->invoiceManagementInterface->prepareInvoice($order);
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                $invoice->register();

                $transactionSave = $this->transaction
                                        ->addObject($invoice)
                                        ->addObject($invoice->getOrder());
                $transactionSave->save();
                $order->setData("base_total_invoiced", $order->getData("base_grand_total"));
                $order->setData("total_invoiced", $order->getData("grand_total"));

                $order->setData("total_paid", $order->getData("grand_total"));
                $order->setData("base_total_paid", $order->getData("grand_total"));
                $order->save();
                $payment = $order->getPayment();
                if ($payment->getId()) {
                    $payment->setData("base_amount_paid", $order->getData("grand_total"));
                    $payment->setData("amount_paid", $order->getData("grand_total"));
                    $payment->save();
                }
                $invoice->setData("base_grand_total", $order->getData("base_grand_total"));
                $invoice->setData("grand_total", $order->getData("grand_total"));

                $invoice->setData("discount_amount", $order->getData("discount_amount"));
                $invoice->setData("base_discount_amount", $order->getData("base_discount_amount"));
                $invoice->save();
            } catch (\Exception $e) {
                throw new \Magento\Framework\Webapi\Exception(__("create invoice " . $e->getMessage()), 0, 400);
            }
        } else {
            $this->results["response"]["data"]["error"][] = 'can not create invoice for order <'. $order->getIncrementId() . '>';
            $this->log('Can not create Invoice for Order <'. $order->getIncrementId() . '>');
        }
    }

    public function createShipment($order, $ioOrderInfo, $shipmentInfo)
    {
        if ($order->hasShipments()) {
            $this->results["response"]["data"]["success"][] = "skip order <" . $order->getIncrementId() . "> for already has shipment";
            $this->log("Skip order <" . $order->getIncrementId() . "> for already has shipment");
            return;
        }

        if ($ioOrderInfo["shipping_status"] != "shipped") {
            $this->results["response"]["data"]["error"][] = "order <" . $order->getIncrementId() . "> is not shipped on io";
            $this->log("Order <" . $order->getIncrementId() . "> is not shipped on IO");
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
                $this->results["response"]["data"]["success"][] = "shipment '" . $shipment->getIncrementId() . "' imported for order <" . $order->getIncrementId() . ">";
                $this->log("Shipment '" . $shipment->getIncrementId() . "' imported for order <" . $order->getIncrementId() . ">");
            } catch (\Exception $e) {
                throw new \Magento\Framework\Webapi\Exception(__("create shipment " . $e->getMessage()), 0, 400);
            }
        }
    }

    public function createCreditMemo($orderIncrementID, $refundInfo)
    {
        $orderObject = $this->orderFactory->create()->loadByIncrementId($orderIncrementID);
        if ($orderObject->hasInvoices()) {
            if (!$orderObject->hasCreditmemos()) {
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
                        foreach ($orderObject->getAllItems() as $orderItem) {
                            if ($orderItem->getSku() == $sku) {
                                $infoItems[$orderItem->getId()] = $qtyRefund;
                            }
                        }
                    }
                    if (!count($infoItems)) {
                        $this->results["response"]["data"]["error"][] = "WARN create credit memo for order <" . $orderObject->getIncrementId() . "> items ordered do not match";
                        $this->log("WARN create credit memo for order <" . $orderObject->getIncrementId() . "> items ordered do not match");
                        return;
                    }
                    $creditMemoData = [
                        'qtys' => $infoItems
                    ];
                    try {
                        $creditMemo = $this->creditmemoFactory->createByOrder($orderObject, $creditMemoData);
                        $creditMemo->save();
                        $shippingRefundedTotal    += (float) $creditMemo->getData("base_shipping_amount");
                        $shippingTaxRefundedTotal += (float) $creditMemo->getData("shipping_tax_amount");
                        $taxRefundedTotal         += (float) $creditMemo->getData("tax_amount");
                        $subtotalRefundedTotal    += (float) $creditMemo->getData("subtotal");
                        $totalRefundedTotal       += (float) $creditMemo->getData("base_grand_total");

                        $this->results["response"]["data"]["success"][] = "credit memo '" . $creditMemo->getIncrementId() . "' imported for order <" . $orderIncrementID . ">";
                        $this->log("Credit Memo '" . $creditMemo->getIncrementId() . "' imported for order <" . $orderIncrementID . ">");
                    } catch (\Exception $e) {
                        $this->results["response"]["data"]["error"][] = "create credit memo " . $e->getMessage();
                        $this->log("ERROR create credit memo " . $e->getMessage());
                        throw new \Magento\Framework\Webapi\Exception(__("create credit memo " . $e->getMessage()), 0, 400);
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
            } else {
                $this->results["response"]["data"]["success"][] = "skip order <" . $orderObject->getIncrementId() . "> has been refunded";
                $this->log("Skip order <" . $orderObject->getIncrementId() . "> has been refunded");
            }
        } else {
            $orderObject->setData("status", "closed");
            $orderObject->setData("state", "closed");
            $orderObject->save();
            $this->results["response"]["data"]["success"][] = "order <" . $orderIncrementID . "> set closed status success";
            $this->log("Order <" . $orderIncrementID . "> set closed status success");
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
