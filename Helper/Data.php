<?php
namespace Conekta\Payments\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleListInterface;

class Data extends AbstractHelper
{
    private $_moduleList;

    protected $_encryptor;

    protected $_productMetadata;

    public function __construct(
        Context $context,
        ModuleListInterface $moduleList,
        EncryptorInterface $encryptor,
        ProductMetadataInterface $productMetadata
    ) {
        parent::__construct($context);
        $this->_moduleList = $moduleList;
        $this->_encryptor = $encryptor;
        $this->_productMetadata = $productMetadata;
    }

    public function getConfigData($area, $field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            'payment/' . $area . '/' . $field,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getModuleVersion()
    {
        return $this->_moduleList->getOne($this->_getModuleName())['setup_version'];
    }

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

    public function getApiVersion()
    {
        return $this->scopeConfig->getValue(
            'conekta/global/api_version',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function pluginType()
    {
        return $this->scopeConfig->getValue(
            'conekta/global/plugin_type',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function pluginVersion()
    {
        return $this->scopeConfig->getValue(
            'conekta/global/plugin_version',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getMageVersion()
    {
        return $this->_productMetadata->getVersion();
    }
}
