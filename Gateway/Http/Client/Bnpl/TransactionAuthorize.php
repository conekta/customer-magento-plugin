<?php
namespace Conekta\Payments\Gateway\Http\Client\Bnpl;

use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;

class TransactionAuthorize implements ClientInterface
{
    /**
     * @var ConektaHelper
     */
    protected $conektaHelper;
    /**
     * @var ConektaLogger
     */
    protected $conektaLogger;
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param ConektaHelper $conektaHelper
     * @param ConektaLogger $conektaLogger
     * @param Logger $logger
     */
    public function __construct(
        ConektaHelper $conektaHelper,
        ConektaLogger $conektaLogger,
        Logger $logger
    ) {
        $this->conektaHelper = $conektaHelper;
        $this->conektaLogger = $conektaLogger;
        $this->logger = $logger;
    }

    /**
     * Authorize transaction
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $request = $transferObject->getBody();
        $this->conektaLogger->info('BNPL Request', $request);

        try {
            \Conekta\Conekta::setApiKey($this->conektaHelper->getPrivateKey());
            \Conekta\Conekta::setApiVersion('2.0.0');
            
            $order = \Conekta\Order::create($request);
            
            $response = [
                'object' => $order,
                'payment_status' => $order->payment_status,
                'charges' => $order->charges,
                'id' => $order->id
            ];

            $this->conektaLogger->info('BNPL Response', $response);

            return $response;
        } catch (\Exception $e) {
            $this->conektaLogger->error('BNPL Error: ' . $e->getMessage());
            
            throw new \Magento\Payment\Gateway\Http\ClientException(
                __('Transaction has been declined. Please try again later.')
            );
        }
    }
}
