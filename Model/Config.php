<?php
namespace Conekta\Payments\Model;

use Conekta\Webhook;
use Conekta\Error;
class Config extends \Magento\Payment\Model\Method\AbstractMethod {
    const CODE = 'conekta_config';
    protected $_code = self::CODE;

    public static  function checkBalance($order, $total) {
        $amount = 0;
        foreach ($order['line_items'] as $lineItem) {
            $amount = $amount +
                ($lineItem['unit_price'] * $lineItem['quantity']);
        }
        foreach ($order['shipping_lines'] as $shippingLine) {
            $amount = $amount + $shippingLine['amount'];
        }
        foreach ($order['discount_lines'] as $discountLine) {
            $amount = $amount - $discountLine['amount'];
        }
        foreach ($order['tax_lines'] as $taxLine) {
            $amount = $amount + $taxLine['amount'];
        }
        if ($amount != $total) {
            $adjustment = $total - $amount;
            $order['tax_lines'][0]['amount'] =
                $order['tax_lines'][0]['amount'] + $adjustment;
            if (empty($order['tax_lines'][0]['description'])) {
                $order['tax_lines'][0]['description'] = 'Round Adjustment';
            }
        }
        return $order;
    }


    public static function getCardToken($info)
    {
        $cardToken = $info->getAdditionalInformation('card_token');
        if (!$cardToken)
            throw new \Magento\Framework\Validator\Exception(__('Error process your card info.'));
        return $cardToken;
    }

    public static function getChargeCard($amount, $tokenId) {
        $charge = array(
            'payment_method' => array(
                'type'     => 'card',
                'token_id' => $tokenId
            ),
            'amount' => $amount
        );
        return $charge;
    }

    public  function createWebhook() {
        $sandboxMode = (boolean) ((integer) $this->getConfigData("sandbox_mode"));
        if ($sandboxMode) {
            $privateKey = (string) $this->getConfigData("test_private_api_key");
        } else {
            $privateKey = (string) $this->getConfigData("live_private_api_key");
        }
        self::initializeConektaLibrary($privateKey);
        $urlWebhook = (string) $this->getConfigData("conekta_webhook");
        if (empty($urlWebhook)) {
            $urlWebhook = \Conekta\Payments\Model\Source\Webhook::getUrl();
        }
        $events = ["events" => ["charge.paid"]];
        $errorMessage = null;
        try {
            $different = true;
            $webhooks = Webhook::where();
            foreach ($webhooks as $webhook) {
                if (strpos($webhook->webhook_url, $urlWebhook) !== false) {
                    $different = false;
                }
            }
            if ($different) {
                if (!$sandboxMode) {
                    $mode = array(
                        "production_enabled" => 1
                    );
                } else {
                    $mode = array(
                        "development_enabled" => 1
                    );
                }
                $webhook = Webhook::create(
                    array_merge(["url" => $urlWebhook], $mode, $events)
                );
            } else {
                throw new \Magento\Framework\Validator\Exception(
                    __('Webhook was already registered in Conekta!<br>URL: ' . $urlWebhook)
                );
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->_logger->error(
                __('[Conekta]: Webhook error, Message: ' . $errorMessage
                    . ' URL: ' . $urlWebhook)
            );
            throw new \Magento\Framework\Validator\Exception(
                __('Can not register this webhook ' . $urlWebhook . '<br>'
                    . 'Message: ' . (string) $errorMessage));
        }
    }

    /**
     * Conekta initializer
     * @throws \Magento\Framework\Validator\Exception
     */
    public static function initializeConektaLibrary($privateKey)
    {
        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resolver = $objectManager->get('Magento\Framework\Locale\Resolver');
            $lang = explode('_', $resolver->getLocale());

            $locale = $lang[0] == 'es' ? 'es' : 'en';

            if (empty($privateKey)) {
                throw new \Magento\Framework\Validator\Exception(
                    __("Please check your conekta config.")
                );
            }
            \Conekta\Conekta::setApiKey($privateKey);
            \Conekta\Conekta::setApiVersion("2.0.0");
            \Conekta\Conekta::setPlugin("Magento 2");
            \Conekta\Conekta::setPluginVersion("2.0.2");
            \Conekta\Conekta::setLocale($locale);
        }catch(\Exception $e){
            throw new \Magento\Framework\Validator\Exception(
                __($e->getMessage())
            );
        }
    }

    /**
     * OXXO Charge getter
     * @param $amount
     * @param $expiryDate
     * @return array
     */
    public static function getChargeOxxo($amount, $expiryDate)
    {
        $charge = array(
            'payment_method' => array(
                'type' => 'oxxo_cash',
                'expires_at' => $expiryDate
            ),
            'amount' => $amount
        );
        return $charge;
    }

    /**
     * SPEI Charge getter
     * @param $amount
     * @param $expiryDate
     * @return array
     */
    public static function getChargeSpei($amount, $expiryDate)
    {
        $charge = array(
            'payment_method' => array(
                'type' => 'spei',
                'expires_at' => $expiryDate
            ),
            'amount' => $amount
        );
        return $charge;
    }

    /**
     * Customer info getter
     * @param $order
     * @return array
     */
    public static function getCustomerInfo($order)
    {
        $billing = $order->getBillingAddress()->getData();
        $customerInfo = [
            'name' => self::getCustomerName($order),
            'email' => $order->getCustomerEmail(),
            'phone' => $billing['telephone'],
            'metadata' => [
                'soft_validations' => true
            ]
        ];
        return $customerInfo;
    }

    /**
     * Line Items getter
     * @param $order
     * @return array
     */
    public static function getLineItems($order)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $lineItems = [];
        $order->getAllVisibleItems();
        $items = $order->getAllVisibleItems();
        foreach ($items as $itemId => $item) {
            if ($item->getProductType() == 'simple' && $item->getPrice() <= 0)
                break;
            $lineItems[] = [
                'name' => $item->getName(),
                'sku' => $item->getSku(),
                'unit_price' => intval(strval($item->getPrice()) * 100),
                'description' => strip_tags($objectManager
                    ->get('Magento\Catalog\Model\Product')
                    ->load($item->getProductId())->getDescription()),
                'quantity' => intval($item->getQtyOrdered()),
                'tags' => [
                    $item->getProductType()
                ]
            ];
        }
        return $lineItems;
    }

    /**
     * Shipping contact getter
     * @param $order
     * @return array
     */
    public static function getShippingContact($order)
    {
        $shippingAddress = $order->getShippingAddress();
        $billing = $order->getBillingAddress()->getData();
        $shippingContact = [];
        if ($shippingAddress) {
            $shippingData = $shippingAddress->getData();
            $shippingContact = [
                'receiver' => self::getCustomerName($order),
                'phone' => $billing['telephone'],
                'address' => [
                    'street1' => $shippingData['street'],
                    'city' => $shippingData['city'],
                    'state' => $shippingData['region'],
                    'country' => $shippingData['country_id'],
                    'postal_code' => $shippingData['postcode'],
                    'phone' => $shippingData['telephone'],
                    'email' => $order->getCustomerEmail()
                ]
            ];
        }
        return $shippingContact;
    }

    /**
     * Shipping lines getter
     * @param $order
     * @return array
     */
    public static function getShippingLines($order)
    {
        $shippingLines = [];
        if ($order->getShippingAmount() > 0) {
            $shippingTax = $order->getShippingTaxAmount();
            $shippingCost = $order->getShippingAmount() + $shippingTax;
            $shippingLines [] = [
                'amount' => intval(strval($shippingCost) * 100),
                'method' => $order->getShippingMethod(),
                'carrier' => $order->getShippingDescription()
            ];
        } else {
            $shippingLines [] = [
                'amount' => 0,
                'method' => $order->getShippingMethod(),
                'carrier' => $order->getShippingDescription()
            ];
        }
        return $shippingLines;
    }

    /**
     * Discount lines getter
     * @param $order
     * @return array
     */
    public static function getDiscountLines($order)
    {
        $discountLines = [];
        $totalDiscount = abs(intval(strval($order->getDiscountAmount()) * 100));
        $totalDiscountCoupons = 0;
        foreach ($order->getAllItems() as $item) {
            if (floatval($item->getDiscountAmount()) > 0.0) {
                $description = $order->getDiscountDescription();
                if (empty($description))
                    $description = "discount_code";
                $discountLine = [];
                $discountLine["code"] = $description;
                $discountLine["type"] = "coupon";
                $discountLine["amount"] = abs(intval(strval($order->getDiscountAmount()) * 100));
                $discountLines =
                    array_merge($discountLines, array($discountLine));
                $totalDiscountCoupons = $totalDiscountCoupons + $discountLine["amount"];
            }
        }
        if (floatval($totalDiscount) > 0.0 && $totalDiscount != $totalDiscountCoupons) {
            $discountLines = [];
            $discountLine = [];
            $discountLine["code"] = "discount";
            $discountLine["type"] = "coupon";
            $discountLine["amount"] = $totalDiscount;
            $discountLines =
                array_merge($discountLines, array($discountLine));
        }
        return $discountLines;
    }

    /**
     * Tax lines getter
     * @param $order
     * @return array
     */
    public static function getTaxLines($order)
    {
        $taxLines = [];
        foreach ($order->getAllItems() as $item) {
            if ($item->getProductType() == 'simple' && $item->getPrice() <= 0)
                break;
            $taxLines[] = [
                'description' => self::getTaxName($item),
                'amount' => intval(strval($item->getTaxAmount()) * 100)
            ];
        }
        return $taxLines;
    }

    /**
     * Tax name getter
     * @param $item
     * @return string
     */
    public static function getTaxName($item)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $_product = $objectManager->get('Magento\Catalog\Model\Product')->load($item->getProductId());
        $taxClassId = $_product->getTaxClassId();
        $taxClass = $objectManager->get('Magento\Tax\Model\ClassModel')->load($taxClassId);
        $taxClassName = $taxClass->getClassName();
        if (empty($taxClassName))
            $taxClassName = "tax";

        return $taxClassName;
    }

    /**
    * Customer name getter
    * @param $order
    * @return string
    */
    public static function getCustomerName($order)
    {
        $billing = $order->getBillingAddress()->getData();
        $customerName = sprintf('%s %s %s', 
            $billing['firstname'], 
            $billing['middlename'], 
            $billing['lastname']
            );
        
        return $customerName;
    }
}