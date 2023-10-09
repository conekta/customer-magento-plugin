<?php
namespace Conekta\Payments\Block\Info;

use Magento\Checkout\Block\Onepage\Success as CompleteCheckout;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;

class Success extends CompleteCheckout
{
    /**
     * GetInstructions getter
     *
     * @param mixed $type
     * @return Order Object
     */
    public function getInstructions($type)
    {
        if ($type == 'cash') {
            return $this->_scopeConfig->getValue(
                'payment/conekta_cash/instructions',
                ScopeInterface::SCOPE_STORE
            );
        } elseif ($type == 'bankTransfer') {
            return $this->_scopeConfig->getValue(
                'payment/conekta_bank_transfer/instructions',
                ScopeInterface::SCOPE_STORE
            );
        }
    }

    /**
     * GetMethod getter
     *
     * @return string Object
     */
    public function getMethod(): string
    {
        return $this->getOrder()->getPayment()->getMethod();
    }

    /**
     * GetOfflineInfo getter
     *
     * @return Order Object
     * @throws LocalizedException
     */
    public function getOfflineInfo()
    {
        return $this->getOrder()
            ->getPayment()
            ->getMethodInstance()
            ->getInfoInstance()
            ->getAdditionalInformation("offline_info");
    }

    /**
     * GetOrder getter
     *
     * @return Order Object
     */
    public function getOrder(): Order
    {
        return $this->_checkoutSession->getLastRealOrder();
    }


    public function getAccountOwner()
    {
        return $this->_scopeConfig->getValue(
            'payment/conekta_bank_transfer/account_owner',
            ScopeInterface::SCOPE_STORE
        );
    }
}
