<?php

namespace Conekta\Payments\Model;

use Magento\Sales\Model\Order;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Conekta\Payments\Model\Config as Config;
use Magento\Payment\Model\InfoInterface as InfoInterface;
use Magento\Framework\Validator\Exception as ValidatorException;

/**
 * Pay in Spei payment method model
 */
class Spei extends Offline
{
    const CODE = 'conekta_spei';
    protected $_code = self::CODE;

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
        array $data = array())
    {
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
    }

    public function authorize(InfoInterface $payment, $amount)
    {
        Config::initializeConektaLibrary($this->_privateKey);

        $order = $payment->getOrder();
        $totalAmount = intval((float)$amount * 1000) / 10;

        $orderData = [
            'currency'              => strtolower($order->getStoreCurrencyCode()),
            'line_items'            => Config::getLineItems($order),
            'shipping_lines'        => Config::getShippingLines($order),
            'discount_lines'        => Config::getDiscountLines($order),
            'tax_lines'             => Config::getTaxLines($order),
            'customer_info'         => Config::getCustomerInfo($order),
            'shipping_contact'      => Config::getShippingContact($order),
            'metadata' => [
                'checkout_id'       => $order->getIncrementId(),
                'soft_validations'  => true            ]
        ];

        $days = $this->getConfigData("expiry_days");
        $chargeExpiration = strtotime("+" . $days . " days");
        $chargeParams = Config::getChargeSpei($totalAmount, $chargeExpiration);

        try {
            $orderData = Config::checkBalance($orderData, $totalAmount);
            $conektaOrder = \Conekta\Order::create($orderData);
            $charge = $conektaOrder->createCharge($chargeParams);
        } catch (ValidatorException $e) {
            $this->_logger->error(__('[Conekta]: Payment capturing error.'));
            throw new ValidatorException(__($e->getMessage()));
        }

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->setExtOrderId($conektaOrder->id);
        $order->setIsTransactionClosed(0);
        $order->save();
        $payment->setTransactionId($charge->id);

        $this->getInfoInstance()->setAdditionalInformation("offline_info", [
            "type" => $this->_code,
            "data" => [
                "clabe" => $charge->payment_method->clabe,
                "bank_name"     => $charge->payment_method->bank,
                "expires_at"    => $charge->payment_method->expires_at
            ]
        ]);
        $payment->setSkipOrderProcessing(true);

        return $this;
    }
}