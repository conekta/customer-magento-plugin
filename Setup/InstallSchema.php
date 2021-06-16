<?php
namespace Conekta\Payments\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
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
					'conekta_order_id',
					\Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
					150,
					[
						'identity' => false,
						'nullable' => false,
						'primary'  => true,
					],
					'Conekta Order'
				)
				->addColumn(
					'order_id',
					\Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
					1,
					[
                        'identity' => false,
						'nullable' => false,
						'primary'  => false,
                    ],
					'Sales Order'
				)
				->setComment('Conekta Orders Table');
			$installer->getConnection()->createTable($table);

		}

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order_grid'),
            'conekta_order_id',
            [
                'type' => Table::TYPE_TEXT,
                'comment' => 'Conekta Order'
            ]
        );

		$installer->endSetup();
	}
}
