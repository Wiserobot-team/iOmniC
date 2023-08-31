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
namespace Wiserobot\Io\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            /*
             * Create table 'wiserobot_io_product_image'
             */
            $table = $setup->getConnection()->newTable(
                $setup->getTable('wiserobot_io_product_image')
            )->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                11,
                array('identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true),
                'ID'
            )->addColumn(
                'sku',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                array('nullable' => false),
                'SKU'
            )->addColumn(
                'store_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                4,
                array('nullable' => false, 'default' => '0'),
                'Store ID'
            )->addColumn(
                'image',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                array('nullable' => true, 'default' => null),
                'Image'
            )->addColumn(
                'image_placement',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                array('nullable' => false, 'default' => null),
                'Image Placement'
            )->setComment(
                'Product Image Table'
            );
            $setup->getConnection()->createTable($table);
        }

        if (version_compare($context->getVersion(), '1.0.2', '<')) {
            /*
             * Create table 'wiserobot_io_order'
             */
            $table = $setup->getConnection()->newTable(
                $setup->getTable('wiserobot_io_order')
            )->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                11,
                array('identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true),
                'ID'
            )->addColumn(
                'order_increment_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                50,
                array('nullable' => false),
                'Order Increment ID'
            )->addColumn(
                'io_order_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                array('nullable' => false),
                'IO Order ID'
            )->addColumn(
                'marketplace',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                array('nullable' => true, 'default' => null),
                'Marketplace'
            )->addColumn(
                'transaction_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                array('nullable' => true, 'default' => null),
                'Transaction ID'
            )->addIndex(
                $setup->getIdxName(
                    'wr_io_order_order_increment_id_index',
                    ['order_increment_id'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
                ),
                ['order_increment_id'],
                ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
            )->addIndex(
                $setup->getIdxName(
                    'wr_io_order_io_order_id_index',
                    ['io_order_id'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
                ),
                ['io_order_id'],
                ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
            )->setComment(
                'Order Table'
            );
            $setup->getConnection()->createTable($table);
        }

        $setup->endSetup();
    }
}
