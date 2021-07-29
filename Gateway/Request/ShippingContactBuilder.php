<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Conekta\Payments\Helper\Data as ConektaHelper;

class ShippingContactBuilder implements BuilderInterface
{
    private $subjectReader;

    private $_conektaLogger;

    public function __construct(
        SubjectReader $subjectReader,
        ConektaLogger $conektaLogger,
        ConektaHelper $conektaHelper
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request ShippingContactBuilder :: __construct');
        $this->subjectReader = $subjectReader;
        $this->_conektaHelper = $conektaHelper;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request ShippingContactBuilder :: build');

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $order = $paymentDO->getOrder();

        $shipping = $order->getShippingAddress();
        
        if ($shipping) {
            $version = (int)str_replace('.', '', $this->_conektaHelper->getMageVersion());
            $street = "";
            if ($version >= 243) {
                $street = $shipping->getStreet()[0];
            } else {
                $street = $shipping->getStreetLine1();
            }

            $request['shipping_contact'] = [
                'receiver' => $this->getCustomerName($shipping),
                'phone' => $shipping->getTelephone(),
                'address' => [
                    'street1' => $street,
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
