<?php
namespace Conekta\Payments\Helper;

use Conekta\Customer;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleListInterface;

/**
 * Class Data
 * @package Conekta\Payments\Helper
 */
class Data extends AbstractHelper
{
    /**
     * @var ModuleListInterface
     */
    protected $_moduleList;
    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;
    /**
     * @var ProductMetadataInterface
     */
    protected $_productMetadata;
    /**
     * @var ConektaLogger
     */
    protected $conektaLogger;
    /**
     * @var Customer
     */
    protected $conektaCustomer;

    /**
     * Data constructor.
     * @param Context $context
     * @param ModuleListInterface $moduleList
     * @param EncryptorInterface $encryptor
     * @param ProductMetadataInterface $productMetadata
     * @param ConektaLogger $conektaLogger
     * @param Customer $conektaCustomer
     */
    public function __construct(
        Context $context,
        ModuleListInterface $moduleList,
        EncryptorInterface $encryptor,
        ProductMetadataInterface $productMetadata,
        ConektaLogger $conektaLogger,
        Customer $conektaCustomer
    ) {
        parent::__construct($context);
        $this->_moduleList = $moduleList;
        $this->_encryptor = $encryptor;
        $this->_productMetadata = $productMetadata;
        $this->conektaLogger = $conektaLogger;
        $this->conektaCustomer = $conektaCustomer;
    }

    /**
     * @param $area
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getConfigData($area, $field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            'payment/' . $area . '/' . $field,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return mixed
     */
    public function getModuleVersion()
    {
        return $this->_moduleList->getOne($this->_getModuleName())['setup_version'];
    }

    /**
     * @return string
     */
    public function getPrivateKey()
    {
        $sandboxMode = $this->getConfigData('conekta/conekta_global', 'sandbox_mode');

        if ($sandboxMode) {
            $privateKey = $this->_encryptor->decrypt($this->getConfigData(
                'conekta/conekta_global',
                'test_private_api_key'
            ));
        } else {
            $privateKey = $this->_encryptor->decrypt($this->getConfigData(
                'conekta/conekta_global',
                'live_private_api_key'
            ));
        }
        return $privateKey;
    }

    /**
     * @return mixed
     */
    public function getPublicKey()
    {
        $sandboxMode = $this->getConfigData('conekta/conekta_global', 'sandbox_mode');
        if ($sandboxMode) {
            $publicKey = $this->getConfigData('conekta/conekta_global', 'test_public_api_key');
        } else {
            $publicKey = $this->getConfigData('conekta/conekta_global', 'live_public_api_key');
        }
        return $publicKey;
    }

    /**
     * @return mixed
     */
    public function getApiVersion()
    {
        return $this->scopeConfig->getValue(
            'conekta/global/api_version',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return mixed
     */
    public function pluginType()
    {
        return $this->scopeConfig->getValue(
            'conekta/global/plugin_type',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return mixed
     */
    public function pluginVersion()
    {
        return $this->scopeConfig->getValue(
            'conekta/global/plugin_version',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getMageVersion()
    {
        return $this->_productMetadata->getVersion();
    }

    /**
     * @param $orderParams
     * @param $chargeParams
     */
    public function deleteSavedCard($orderParams, $chargeParams)
    {
        $this->conektaLogger->info('deleteSavedCard: Remove Decline Card From Conekta Customer');

        try {
            $paymentSourceId = isset($chargeParams['payment_method']['payment_source_id']) ? $chargeParams['payment_method']['payment_source_id'] : '';
            $customerId = isset($orderParams['customer_info']['customer_id']) ? $orderParams['customer_info']['customer_id'] : '';
            if ($customerId && $paymentSourceId) {
                $customer = $this->conektaCustomer->find($customerId);
                $customer->deletePaymentSourceById($paymentSourceId);
            }
        } catch (\Conekta\ProcessingError $error) {
            $this->conektaLogger->info($error->getMessage());
        } catch (\Conekta\ParameterValidationError $error) {
            $this->conektaLogger->info($error->getMessage());
        } catch (\Conekta\Handler $error) {
            $this->conektaLogger->info($error->getMessage());
        }
        return;
    }
}
