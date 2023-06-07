<?php

namespace Conekta\Payments\Model;

use Conekta\Payments\Api\ConektaApiClient;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Validator\Exception;

class Config
{
    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;
    /**
     * @var ConektaHelper
     */
    protected $_conektaHelper;
    /**
     * @var ConektaLogger
     */
    private $_conektaLogger;
    /**
     * @var Resolver
     */
    protected $_resolver;
    /**
     * @var ConektaApiClient
     */
    protected $conektaApiClient;

    /**
     * @param EncryptorInterface $encryptor
     * @param ConektaHelper $conektaHelper
     * @param Resolver $resolver
     * @param ConektaLogger $conektaLogger
     */
    public function __construct(
        EncryptorInterface $encryptor,
        ConektaHelper      $conektaHelper,
        Resolver           $resolver,
        ConektaLogger      $conektaLogger,
        ConektaApiClient   $conektaApiClient
    )
    {
        $this->_encryptor = $encryptor;
        $this->_conektaHelper = $conektaHelper;
        $this->_resolver = $resolver;
        $this->_conektaLogger = $conektaLogger;
    }

    /**
     * Create Webhook
     *
     * @return void
     * @throws Exception
     */
    public function createWebhook()
    {

        try {
            $sandboxMode = $this->_conektaHelper->getConfigData('conekta/conekta_global', 'sandbox_mode');
            $urlWebhook = $this->_conektaHelper->getUrlWebhookOrDefault();

            $events = ["events" => ["charge.paid"]];
            $errorMessage = null;

            $different = true;
            $webhooks = $this->conektaApiClient->getWebhooks();
            foreach ($webhooks as $webhook) {
                if (strpos($webhook->getUrl(), $urlWebhook) !== false) {
                    $different = false;
                }
            }
            if ($different) {
                $webhookResponse = $this->conektaApiClient->createWebhook([
                    'url' => $urlWebhook
                ]);
                //$this->conektaApiClient->updateWebhook($webhookResponse->getId(), array_merge(["url" => $urlWebhook], $mode, $events))
            } else {
                $this->_conektaLogger->info('[Conekta]: El webhook ' . $urlWebhook . ' ya se encuentra en Conekta!');
            }
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->_conektaLogger->info('[Conekta]: CreateWebhook error, Message: ' . $errorMessage);

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->_conektaLogger->info('[Conekta]: Webhook error, Message: ' . $errorMessage . ' URL: ' . $urlWebhook);

            throw new Exception(
                __('Can not register this webhook ' . $urlWebhook . '<br>'
                    . 'Message: ' . (string)$errorMessage)
            );
        }
    }
}
