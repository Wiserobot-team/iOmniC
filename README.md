## iOmniC
iOmniC provides near real-time connectivity to multiple platforms (Rithum, Magento 2, etc.).
No more time consuming manual updates necessary. All your Products, Orders, and Fulfillment are automatically synced between platforms within minutes. Our admin UI will help you track the status, details, timestamps, and completion levels of your data. Any transfer errors will show in the dashboard and via email digest with hints on how to fix in your data. You decide the flow of data, and can create/enable/disable/remove any automated process easily.

Near real-time synchronization

Easily monitor your connections

Granular integration controls

## Installation

Run the command below to install via Composer

```shell
composer require wiserobot/module-iomnic
```

## Enable module

To enable the module do the following

```shell
php bin/magento module:enable WiseRobot_Io

php bin/magento setup:upgrade

php bin/magento setup:di:compile

php bin/magento setup:static-content:deploy -f
```

## Disable module

To disable the module follow the instructions below

```shell
php bin/magento module:disable WiseRobot_Io

php bin/magento setup:di:compile

php bin/magento setup:upgrade

php bin/magento setup:static-content:deploy -f
```
