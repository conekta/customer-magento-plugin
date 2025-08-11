<?php
namespace Conekta\Payments\Gateway\Config\Bnpl;

use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\Method\AbstractMethod;

/**
 * Payment Action Value Handler
 */
class PaymentActionValueHandler implements ValueHandlerInterface
{
    /**
     * Retrieve method configured value
     *
     * @param array $subject
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function handle(array $subject, $storeId = null)
    {
        /** @var PaymentDataObjectInterface $paymentDO */
        $paymentDO = $subject['payment'];

        return AbstractMethod::ACTION_AUTHORIZE;
    }
}
