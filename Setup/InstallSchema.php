<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Vsynch\LimitSales\Setup;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * @codeCoverageIgnore
 */
class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        /**
         * Create table 'limit_sales_instances'
         */
        $table = $setup->getConnection()
            ->newTable($setup->getTable('limit_sales_instances'))
            ->addColumn(
                'instance_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
                'Instance ID'
            )
            ->addColumn(
                'user_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => false, 'unsigned' => true, 'nullable' => false, 'primary' => false],
                'User ID'
            )
            ->addColumn(
                'product_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['identity' => false, 'unsigned' => true, 'nullable' => false, 'primary' => false],
                'Product ID'
            )
            ->addColumn(
                'sales',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                null,
                ['nullable' => false],
                'Sale Quantities And Time Stamps'
            )
            ->addColumn(
                'sales_start_time',
                \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                null,
                ['nullable' => true],
                'Sale Start'
            )
            ->addColumn(
                'sales_end_time',
                \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                null,
                ['nullable' => true],
                'Sale End'
            )
            ->addForeignKey('uid','user_id','customer_entity','entity_id',\Magento\Framework\DB\Ddl\Table::ACTION_CASCADE)
            ->addForeignKey('pid','product_id','catalog_product_entity','entity_id',\Magento\Framework\DB\Ddl\Table::ACTION_CASCADE)
            ->setComment("Tracks Product Sales for Limit Sales");
        $setup->getConnection()->createTable($table);
    }
}
