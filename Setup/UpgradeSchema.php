<?php
namespace Conekta\Payments\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;

class UpgradeSchema implements UpgradeSchemaInterface
{

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '3.6.0', '<')) {
            if (!$installer->tableExists('conekta_salesorder')) {
                $this->addConektaSalesOrderTable($setup);
            }
        }
        if (version_compare($context->getVersion(), '3.6.1', '<')) {
            if (!$installer->tableExists('conekta_quote')) {
                $this->addConektaOrderQuote($setup);
            }
        }
        
        $installer->endSetup();
    }

    private function addConektaSalesOrderTable(SchemaSetupInterface $setup)
    {
        $installer = $setup;
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
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['conekta_order_id', 'increment_order_id'],
            AdapterInterface::INDEX_TYPE_UNIQUE
        );
    }

    private function addConektaOrderQuote(SchemaSetupInterface $setup)
    {
        $installer = $setup;
        $table = $installer->getConnection()->newTable(
            $installer->getTable('conekta_quote')
        )
        ->addColumn(
            'quote_id',
            Table::TYPE_INTEGER,
            null,
            [
                'identity' => false,
                'nullable' => false,
                'primary'  => true,
                'unsigned'  => true
            ],
            'Conekta Quote ID'
        )
        ->addColumn(
            'conekta_order_id',
            Table::TYPE_TEXT,
            150,
            ['nullable' => false],
            'Conekta Order'
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
        ->addForeignKey(
            $installer->getFkName('conekta_quote', 'quote_id', 'quote', 'entity_id'),
            'quote_id',
            $installer->getTable('quote'),
            'entity_id',
            Table::ACTION_CASCADE
        )
        ->setComment('Map Table Conekta Orders and Quotes');
        $installer->getConnection()->createTable($table);

        $installer->getConnection()->addIndex(
            $installer->getTable('conekta_quote'),
            $setup->getIdxName(
                $installer->getTable('conekta_quote'),
                ['conekta_order_id'],
                AdapterInterface::INDEX_TYPE_INDEX
            ),
            ['conekta_order_id'],
            AdapterInterface::INDEX_TYPE_UNIQUE
        );
    }
}
