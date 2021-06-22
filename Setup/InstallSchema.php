<?php
namespace Conekta\Payments\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        if (!$installer->tableExists('conekta_salesorder')) {
            $table = $installer->getConnection()->newTable(
                $installer->getTable('conekta_salesorder')
            )
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'nullable' => false,
                        'primary'  => true,
                        'unsigned'  => true
                    ],
                    'Conekta Sales Order ID'
                )
                ->addColumn(
                    'conekta_order_id',
                    Table::TYPE_TEXT,
                    150,
                    ['nullable' => false],
                    'Conekta Order'
                )
                ->addColumn(
                    'increment_order_id',
                    Table::TYPE_TEXT,
                    150,
                    ['nullable' => false],
                    'Sales Order Increment Id'
                )
                ->addColumn(
                    'created_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                    'Created At'
                )->addColumn(
                    'updated_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
                    'Updated At'
                )
                ->setComment('Conekta Orders Table');
            $installer->getConnection()->createTable($table);

            $installer->getConnection()->addIndex(
                $installer->getTable('conekta_salesorder'),
                $setup->getIdxName(
                    $installer->getTable('conekta_salesorder'),
                    ['conekta_order_id', 'increment_order_id'],
                    AdapterInterface::INDEX_TYPE_FULLTEXT
                ),
                ['conekta_order_id', 'increment_order_id'],
                AdapterInterface::INDEX_TYPE_FULLTEXT
            );

        }
        
        $installer->endSetup();
    }
}
