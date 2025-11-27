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
     * @param string $type
     * @return mixed|void
     */
    public function getInstructions(string $type)
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
        } elseif ($type == 'bnpl') {
            return $this->_scopeConfig->getValue(
                'payment/conekta_bnpl/instructions',
                ScopeInterface::SCOPE_STORE
            );
        } elseif ($type == 'payByBank') {
            return $this->_scopeConfig->getValue(
                'payment/conekta_pay_by_bank/instructions',
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

    /**
     * Get Pay By Bank redirect URL
     *
     * @return string|null
     * @throws LocalizedException
     */
    public function getPayByBankRedirectUrl()
    {
        $additionalInfo = $this->getOrder()
            ->getPayment()
            ->getAdditionalInformation();
        
        return $additionalInfo['redirect_url'] ?? null;
    }

    /**
     * Get Pay By Bank deep link
     *
     * @return string|null
     * @throws LocalizedException
     */
    public function getPayByBankDeepLink()
    {
        $additionalInfo = $this->getOrder()
            ->getPayment()
            ->getAdditionalInformation();
        
        return $additionalInfo['deep_link'] ?? null;
    }
}
