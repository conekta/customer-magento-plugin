<?php

namespace Conekta\Payments\Model;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Store\Model\ScopeInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\Data\Collection\AbstractDb;

class Offline extends AbstractMethod {
	/**
     * Payment code
     *
     * @var string
     */
    
    protected $_countryFactory;
    protected $_minAmount = null;
    protected $_maxAmount = null;
    protected $_scopeConfig;
    protected $_isSandbox = true;
    protected $_privateKey = null;

    protected $_infoBlockType = 'Conekta\Payments\Block\Info\Custom';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;

    public function __construct(
        Context $context, 
        Registry $registry, 
        ExtensionAttributesFactory $extensionFactory, 
        AttributeValueFactory $customAttributeFactory, 
        Data $paymentData, 
        ScopeConfigInterface $scopeConfig, 
        Logger $logger, 
        AbstractResource $resource = null, 
        AbstractDb $resourceCollection = null, 
        array $data = array()){
        
        parent::__construct(
            $context, 
            $registry, 
            $extensionFactory, 
            $customAttributeFactory, 
            $paymentData, 
            $scopeConfig, 
            $logger, 
            $resource, 
            $resourceCollection, 
            $data);
        
        $this->_scopeConfig = $scopeConfig;

        if (!class_exists('\\Conekta\\Payments\\Model\\Config')) {
            throw new \Magento\Framework\Validator\Exception(__("Class Conekta\\Payments\\Model\\Config not found."));
        }
        
        $this->_isSandbox = (boolean)((integer)$this->_getConektaConfig('sandbox_mode'));
        
        if ($this->_isSandbox) {
            $privateKey = (string) $this->_getConektaConfig('test_private_api_key');
        } else {
            $privateKey = (string) $this->_getConektaConfig('live_private_api_key');
        }
        
        if (!empty($privateKey)) {
            $this->_privateKey = $privateKey;
            unset($privateKey);
        } else {
            $this->_logger->error(__('Please set Conekta API keys in your admin.'));
        }
        
        $this->_minAmount = $this->getConfigData('min_order_total');
        $this->_maxAmount = $this->getConfigData('max_order_total');
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {
        if ($quote && (
            $quote->getBaseGrandTotal() < $this->_minAmount
            || ($this->_maxAmount && $quote->getBaseGrandTotal() > $this->_maxAmount))
            ) {
            return false;
        }
        
        if (empty($this->_privateKey)) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    protected function _getConektaConfig($field) {
        $path = 'payment/' . \Conekta\Payments\Model\Config::CODE . '/' . $field;
        return $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, parent::getStore());
    }
    
    public function getInstructions(){
        $parts = explode("_", $this->_code);
        $method = $parts[1];
        
        return (string) $this->getConfigData($method . "_instructions");
    }
}