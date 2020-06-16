<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ShippingContactBuilder implements BuilderInterface
{
    private $subjectReader;

    private $_conektaLogger;

    public function __construct(
        SubjectReader $subjectReader,
        ConektaLogger $conektaLogger
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request ShippingContactBuilder :: __construct');
        $this->subjectReader = $subjectReader;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request ShippingContactBuilder :: build');

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $order = $paymentDO->getOrder();

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

        $this->_conektaLogger->info('Request ShippingContactBuilder :: build : return request', $request);

        return $request;
    }

    public function getCustomerName($shipping)
    {
        $customerName = sprintf(
            '%s %s %s',
            $shipping->getFirstname(),
            $shipping->getMiddlename(),
            $shipping->getLastname()
        );

        return $customerName;
    }
}
