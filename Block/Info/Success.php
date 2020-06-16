<?php
namespace Conekta\Payments\Block\Info;

use Magento\Checkout\Block\Onepage\Success as CompleteCheckout;

class Success extends CompleteCheckout
{
    /**
     * getInstructions getter
     * @return Order Object
     */
    public function getInstructions($type)
    {
        if ($type == 'oxxo') {
            return $this->_scopeConfig->getValue(
                'payment/conekta_oxxo/instructions',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        } elseif ($type == 'spei') {
            return $this->_scopeConfig->getValue(
                'payment/conekta_spei/instructions',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        }
    }
    /**
     * getMethod getter
     * @return Order Object
     */
    public function getMethod()
    {
        return $this->getOrder()->getPayment()->getMethod();
    }
    /**
     *  getOfflineInfo getter
     * @return Order Object
     */
    public function getOfflineInfo()
    {
        $offline_info = $this->getOrder()
            ->getPayment()
            ->getMethodInstance()
            ->getInfoInstance()
            ->getAdditionalInformation("offline_info");

        return $offline_info;
    }
    /**
     * getOrder getter
     * @return Order Object
     */
    public function getOrder()
    {
        return $this->_checkoutSession->getLastRealOrder();
    }
    /**
     * getAccountOwner getter
     * @return Store Instance
     */
    public function getAccountOwner()
    {
        return $this->_scopeConfig->getValue(
            'payment/conekta_spei/account_owner',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
