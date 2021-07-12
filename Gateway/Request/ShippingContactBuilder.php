<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Magento\Framework\Exception\LocalizedException;

class ShippingContactBuilder implements BuilderInterface
{
    private $subjectReader;

    private $_conektaLogger;

    private $_conektaHelper;

    public function __construct(
        SubjectReader $subjectReader,
        ConektaLogger $conektaLogger,
        ConektaHelper $_conektaHelper
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request ShippingContactBuilder :: __construct');
        $this->subjectReader = $subjectReader;
        $this->_conektaHelper = $_conektaHelper;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request ShippingContactBuilder :: build');

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();
        $quoteId = $payment->getAdditionalInformation('quote_id');
        /*
        $shipping = $order->getShippingAddress();
        if ($shipping) {
            $request['shipping_contact'] = [
                'receiver' => $this->getCustomerName($shipping),
                'phone' => $shipping->getTelephone(),
                'address' => [
                    'street1' => $shipping->getStreetLine1(),
                    'city' => $shipping->getCity(),
                    'state' => $shipping->getRegionCode(),
                    'country' => $shipping->getCountryId(),
                    'postal_code' => $shipping->getPostcode(),
                    'phone' => $shipping->getTelephone(),
                    'email' => $shipping->getEmail()
                ]
            ];
        } else {
            $request['shipping_contact'] = [];
        }
        */

        $request['shipping_contact'] = $this->_conektaHelper->getShippingContact($quoteId);

        if(empty($request['shipping_contact'])){
            throw new LocalizedException(__('Missing shipping contacta information'));
        }

        $this->_conektaLogger->info('Request ShippingContactBuilder :: build : return request', $request);

        return $request;
    }

}
