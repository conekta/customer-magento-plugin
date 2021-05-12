<?php

namespace Conekta\Payments\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchInterface;

/**
 * Class AddCustomerConektaAttr
 * @package Conekta\Payments\Setup\Patch\Data
 */
class AddCustomerConektaAttr implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    protected $moduleDataSetup;
    /**
     * @var CustomerSetupFactory
     */
    protected $customerSetupFactory;

    /**
     * AddCustomerErpCustomerIdAttribute constructor.
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerSetupFactory $customerSetupFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerSetupFactory $customerSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerSetupFactory = $customerSetupFactory;
    }

    /**
     * Get array of patches that have to be executed prior to this.
     * @return string[]
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Get aliases (previous names) for the patch.
     *
     * @return string[]
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * Run code inside patch
     * If code fails, patch must be reverted, in case when we are speaking about schema - than under revert
     * means run PatchInterface::revert()
     *
     * If we speak about data, under revert means: $transaction->rollback()
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function apply()
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'conekta_customer_id',
            [
                'type' => 'varchar',
                'label' => 'Conekta Customer Id',
                'input' => 'text',
                'required' => false,
                'sort_order' => 87,
                'visible' => true,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                'system' => 0
            ]
        );
        $erpAttribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'conekta_customer_id');
        $erpAttribute->setData(
            'used_in_forms',
            ['adminhtml_customer']
        );
        $erpAttribute->save();
    }
}
