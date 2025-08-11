<?php
namespace Conekta\Payments\Gateway\Request\Bnpl;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class AuthorizeRequest implements BuilderInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;
    /**
     * @var ConektaHelper
     */
    protected $conektaHelper;

    /**
     * Constructor
     *
     * @param ConfigInterface $config
     * @param ConektaHelper $conektaHelper
     */
    public function __construct(
        ConfigInterface $config,
        ConektaHelper $conektaHelper
    ) {
        $this->config = $config;
        $this->conektaHelper = $conektaHelper;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment'])
            || !$buildSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $payment */
        $paymentDO = $buildSubject['payment'];
        /** @var Order $order */
        $order = $paymentDO->getOrder();
        $payment = $paymentDO->getPayment();

        $request = [
            'currency' => $order->getCurrencyCode(),
            'customer_info' => [], // Will be built by CustomerInfoBuilder
            'line_items' => [], // Will be built by LineItemsBuilder
            'shipping_lines' => [], // Will be built by ShippingLinesBuilder
            'discount_lines' => [], // Will be built by DiscountLinesBuilder
            'shipping_contact' => [], // Will be built by ShippingContactBuilder
            'tax_lines' => [], // Will be built by TaxLinesBuilder
            'metadata' => [], // Will be built by MetadataBuilder
            'charges' => [
                [
                    'payment_method' => [
                        'type' => 'bnpl',
                        'monthly_installments' => $this->getMonthlyInstallments($order->getGrandTotal())
                    ]
                ]
            ]
        ];

        return $request;
    }

    /**
     * Get monthly installments based on amount
     *
     * @param float $amount
     * @return int
     */
    private function getMonthlyInstallments($amount)
    {
        // Default installments based on amount
        if ($amount >= 5000) {
            return 12;
        } elseif ($amount >= 2000) {
            return 6;
        } else {
            return 3;
        }
    }
}
