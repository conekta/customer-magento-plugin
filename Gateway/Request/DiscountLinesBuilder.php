<?php
namespace Conekta\Payments\Gateway\Request;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class DiscountLinesBuilder implements BuilderInterface
{
    private $_conektaLogger;

    private $subjectReader;

    protected $_cartRepository;

    public function __construct(
        ConektaLogger $conektaLogger,
        SubjectReader $subjectReader,
        CartRepositoryInterface $cartRepository
    ) {
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Request DiscountLinesBuilder :: __construct');
        $this->subjectReader = $subjectReader;
        $this->_cartRepository = $cartRepository;
    }

    public function build(array $buildSubject)
    {
        $this->_conektaLogger->info('Request DiscountLinesBuilder :: build');

        $paymentDO = $this->subjectReader->readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $quote_id = $payment->getAdditionalInformation('quote_id');
        $quote = $this->_cartRepository->get($quote_id);
        $totalDiscount = $quote->getSubtotal() - $quote->getSubtotalWithDiscount();

        if (!empty($totalDiscount)) {
            $totalDiscount = abs((int)(round($totalDiscount, 2) * 100));
            $discountLine["code"] = "discount_code";
            $discountLine["type"] = "coupon";
            $discountLine["amount"] = $totalDiscount;
            $request['discount_lines'][] = $discountLine;
        } else {
            $request['discount_lines'] = [];
        }

        $this->_conektaLogger->info('Request DiscountLinesBuilder :: build : return request', $request);

        return $request;
    }

    private function _mergeLines($lines, $line)
    {
        return array_merge($lines, [$line]);
    }
}
