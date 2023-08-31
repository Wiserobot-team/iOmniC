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
namespace WiseRobot\Io\Setup;

use Zend\Log\Writer\Stream;
use Zend\Log\Logger;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Setup\SalesSetupFactory;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Catalog\Model\Config;

class UpgradeData implements UpgradeDataInterface
{
    protected $filesystem;
    protected $salesSetupFactory;
    protected $eavSetupFactory;
    protected $config;

    public function __construct(
        Filesystem               $filesystem,
        SalesSetupFactory        $salesSetupFactory,
        EavSetupFactory          $eavSetupFactory,
        Config                   $config
    ) {
        $this->filesystem        = $filesystem;
        $this->salesSetupFactory = $salesSetupFactory;
        $this->eavSetupFactory   = $eavSetupFactory;
        $this->config            = $config;
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.3', '<')) {
            /** @var SalesSetup $salesSetup */
            $salesSetup   = $this->salesSetupFactory->create(['setup' => $setup]);

            $attributes   = [];
            $attributes[] = [
                'name'    => 'io_order_id',
                'label'   => 'Site Order#',
                'type'    => 'text',
                'input'   => 'text',
                'source'  => '',
                'default' => '',
                'grid'    => true,
            ];

            $attributes[] = [
                'name'    => 'io_marketplace',
                'label'   => 'Marketplace',
                'type'    => 'text',
                'input'   => 'text',
                'source'  => '',
                'default' => '',
                'grid'    => true,
            ];

            foreach ($attributes as $attr) {
                $salesSetup->addAttribute(
                    'order',
                    $attr['name'],
                    [
                        'name'       => $attr['name'],
                        'label'      => $attr['label'],
                        'type'       => $attr['type'],
                        'visible'    => true,
                        'required'   => false,
                        'unique'     => false,
                        'filterable' => 1,
                        'sort_order' => 800,
                        'default'    => $attr['default'],
                        'input'      => $attr['input'],
                        'source'     => $attr['source'],
                        'grid'       => $attr['grid'],
                    ]
                );
                $usedInForms = [
                    'adminhtml_order',
                ];
            }
        }

        if (version_compare($context->getVersion(), '1.0.4', '<')) {
            /** @var SalesSetup $salesSetup */
            $salesSetup   = $this->salesSetupFactory->create(['setup' => $setup]);

            $attributes   = [];
            $attributes[] = [
                'name'    => 'ca_order_id',
                'label'   => 'ChannelAdvisor Order#',
                'type'    => 'text',
                'input'   => 'text',
                'source'  => '',
                'default' => '',
                'grid'    => true,
            ];

            foreach ($attributes as $attr) {
                $salesSetup->addAttribute(
                    'order',
                    $attr['name'],
                    [
                        'name'       => $attr['name'],
                        'label'      => $attr['label'],
                        'type'       => $attr['type'],
                        'visible'    => true,
                        'required'   => false,
                        'unique'     => false,
                        'filterable' => 1,
                        'sort_order' => 800,
                        'default'    => $attr['default'],
                        'input'      => $attr['input'],
                        'source'     => $attr['source'],
                        'grid'       => $attr['grid'],
                    ]
                );
                $usedInForms = [
                    'adminhtml_order',
                ];
            }
        }

        if (version_compare($context->getVersion(), '1.0.5', '<')) {
            /** @var SalesSetup $salesSetup */
            $salesSetup   = $this->salesSetupFactory->create(['setup' => $setup]);

            $attributes   = [];
            $attributes[] = [
                'name'    => 'buyer_user_id',
                'label'   => 'Buyer User#',
                'type'    => 'text',
                'input'   => 'text',
                'source'  => '',
                'default' => '',
                'grid'    => true,
            ];

            foreach ($attributes as $attr) {
                $salesSetup->addAttribute(
                    'order',
                    $attr['name'],
                    [
                        'name'       => $attr['name'],
                        'label'      => $attr['label'],
                        'type'       => $attr['type'],
                        'visible'    => true,
                        'required'   => false,
                        'unique'     => false,
                        'filterable' => 1,
                        'sort_order' => 800,
                        'default'    => $attr['default'],
                        'input'      => $attr['input'],
                        'source'     => $attr['source'],
                        'grid'       => $attr['grid'],
                    ]
                );
                $usedInForms = [
                    'adminhtml_order',
                ];
            }
        }

        $setup->endSetup();
    }

    public function log($message)
    {
        $logDir = $this->filesystem->getDirectoryWrite(DirectoryList::LOG);
        $writer = new Stream($logDir->getAbsolutePath('') . "wiserobotio_install.log");
        $logger = new Logger();
        $logger->addWriter($writer);
        $logger->info(print_r($message, true));
    }
}
