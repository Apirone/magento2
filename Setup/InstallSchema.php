<?php

namespace Apirone\Merchant\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     */

    public function install(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $installer = $setup;
        $installer->startSetup();

        $salesTableName = $installer->getTable('apirone_sale');
        $transactionsTableName = $installer->getTable('apirone_transactions');

    if ($installer->getConnection()->isTableExists($salesTableName) != true) {
            $salesTable = $installer->getConnection()
                ->newTable($salesTableName)
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true
                    ],
                    'ID'
                )
                ->addColumn(
                    'time',
                    Table::TYPE_DATETIME,
                    null,
                    [ 'nullable' => false ],
                    'time stamp'
                )
                ->addColumn(
                    'address',
                    Table::TYPE_TEXT,
                    null,
                    [ 'nullable' => false ],
                    'Bitcoin Address'
                )
                ->addColumn(
                    'order_id',
                    Table::TYPE_INTEGER,
                    null,
                    [ 'unsigned' => true, 'nullable' => false ],
                    'Order ID'
                )
                ->setComment('Apirone Sales Table')
                ->setOption('type', 'InnoDB')
                ->setOption('charset', 'utf8');

            $installer->getConnection()->createTable($salesTable);
        }
    if ($installer->getConnection()->isTableExists($transactionsTableName) != true) {
            $transactionsTable = $installer->getConnection()
                ->newTable($transactionsTableName)
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true
                    ],
                    'ID'
                )
                ->addColumn(
                    'time',
                    Table::TYPE_DATETIME,
                    null,
                    [ 'nullable' => false ],
                    'time stamp'
                )
                ->addColumn(
                    'paid',
                    Table::TYPE_FLOAT,
                    null,
                    ['nullable' => false ],
                    'Transaction value in BTC'
                )
                ->addColumn(
                    'confirmations',
                    Table::TYPE_INTEGER,
                    null,
                    ['nullable' => false ],
                    'Confirmations count'
                )
                ->addColumn(
                    'thash',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false ],
                    'Transaction ID'
                )
                ->addColumn(
                    'input_thash',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false ],
                    'Transaction ID'
                )
                ->addColumn(
                    'order_id',
                    Table::TYPE_INTEGER,
                    null,
                    [ 'unsigned' => true, 'nullable' => false ],
                    'Order ID'
                )
                ->setComment('Apirone Transactions Table')
                ->setOption('type', 'InnoDB')
                ->setOption('charset', 'utf8');

        $installer->getConnection()->createTable($transactionsTable);
    }

        $setup->endSetup();
    }
}
