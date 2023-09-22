## iOmniC
iOmniC provides near real-time connectivity to multiple platforms (ChannelAdvisor, Magento 2, etc.).
No more time consuming manual updates necessary. All your Products, Orders, and Fulfillment are automatically synced between platforms within minutes. Our admin UI will help you track the status, details, timestamps, and completion levels of your data. Any transfer errors will show in the dashboard and via email digest with hints on how to fix in your data. You decide the flow of data, and can create/enable/disable/remove any automated process easily.

Near real-time synchronization

Easily monitor your connections

Granular integration controls

## Installation

Run the command below to install via Composer

```shell
composer require wiserobot/module-io
```

## Enable module

You have to enable module

```shell
php bin/mangento module:enable Wiserobot_Io

php bin/magento setup:upgrade

php bin/magento setup:di:compile

php bin/magento setup:static-content:deploy -f
```

## Disable module

If you wanna disable module. You can follow script bellow

```shell
php bin/mangento module:disable Wiserobot_Io

php bin/magento setup:di:compile

php bin/magento setup:upgrade

php bin/magento setup:static-content:deploy -f
```
## How to sync data to iOmniC
First, You go to the site [iOmniC](https://app.iomnic.com/) to create an account or login (already account)

In left sidebar, You click to the **Connections**
  - **Add new target** in content

## How to create an Integration on Magento admin
You go to **System** > **Integrations**  and create a new Integrations for **iOmniC**
## License

Released under the MIT License attached with this code.
