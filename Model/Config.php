<?php

namespace Conekta\Payments\Model;

use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validator\Exception;

class Config
{

    /**
     * @var ConektaHelper
     */
    protected ConektaHelper $_conektaHelper;
    /**
     * @var ConektaLogger
     */
    private ConektaLogger $_conektaLogger;

    /**
     * @var ConektaApiClient
     */
    protected ConektaApiClient $conektaApiClient;

    /**
     * @param ConektaHelper $conektaHelper
     * @param ConektaLogger $conektaLogger
     * @param ConektaApiClient $conektaApiClient
     */
    public function __construct(
        ConektaHelper      $conektaHelper,
        ConektaLogger      $conektaLogger,
        ConektaApiClient   $conektaApiClient
    )
    {
        $this->_conektaHelper = $conektaHelper;
        $this->_conektaLogger = $conektaLogger;
        $this->conektaApiClient = $conektaApiClient;
    }

    /**
     * Create Webhook
     *
     * @return void
     * @throws Exception|NoSuchEntityException
     */
    public function createWebhook()
    {
        $urlWebhook = $this->_conektaHelper->getUrlWebhookOrDefault();
        try {
            $different = true;
            $webhooks = $this->conektaApiClient->getWebhooks();
            $data = $webhooks->getData();
            foreach ($data as $webhook) {
                if (strpos($webhook->getUrl(), $urlWebhook) !== false) {
                    $different = false;
                }
            }
            if ($different) {
                $this->conektaApiClient->createWebhook([
                    'url' => $urlWebhook
                ]);
            } else {
                $this->_conektaLogger->info('[Conekta]: El webhook ' . $urlWebhook . ' ya se encuentra en Conekta!');
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->_conektaLogger->info('[Conekta]: Webhook error, Message: ' . $errorMessage . ' URL: ' . $urlWebhook);

            throw new Exception(
                __('Can not register this webhook ' . $urlWebhook . '<br>'
                    . 'Message: ' . $errorMessage)
            );
        }
    }
}
