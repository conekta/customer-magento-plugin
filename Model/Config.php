<?php
namespace Conekta\Payments\Model;

use Conekta\Conekta;
use Conekta\Payments\Helper\Data as ConektaHelper;
use Conekta\Payments\Logger\Logger as ConektaLogger;
use Conekta\Webhook;
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
     * @var Webhook
     */
    protected $_conektaWebhook;

    /**
     * @param EncryptorInterface $encryptor
     * @param ConektaHelper $conektaHelper
     * @param Resolver $resolver
     * @param ConektaLogger $conektaLogger
     * @param Webhook $conektaWebhook
     */
    public function __construct(
        EncryptorInterface $encryptor,
        ConektaHelper $conektaHelper,
        Resolver $resolver,
        ConektaLogger $conektaLogger,
        Webhook $conektaWebhook
    ) {
        $this->_encryptor = $encryptor;
        $this->_conektaHelper = $conektaHelper;
        $this->_resolver = $resolver;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaWebhook = $conektaWebhook;
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

            //If library can't be initialized throws exception
            $this->initializeConektaLibrary();

            $different = true;
            $webhooks = $this->_conektaWebhook->where();
            foreach ($webhooks as $webhook) {
                if (strpos($webhook->webhook_url, $urlWebhook) !== false) {
                    $different = false;
                }
            }
            if ($different) {
                if (!$sandboxMode) {
                    $mode = [
                        "production_enabled" => 1
                    ];
                } else {
                    $mode = [
                        "development_enabled" => 1
                    ];
                }
                $this->_conektaWebhook->create(
                    array_merge(["url" => $urlWebhook], $mode, $events)
                );
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
                    . 'Message: ' . (string) $errorMessage)
            );
        }
    }

    /**
     * Initialize conekta library
     *
     * @return void
     * @throws Exception
     */
    public function initializeConektaLibrary()
    {
        try {
            $lang = explode('_', $this->_resolver->getLocale());
            $locale = $lang[0] == 'es' ? 'es' : 'en';
            $privateKey = $this->_conektaHelper->getPrivateKey();
            $apiVersion = $this->_conektaHelper->getApiVersion();
            $pluginType = $this->_conektaHelper->pluginType();
            $pluginVersion = $this->_conektaHelper->pluginVersion();

            if (empty($privateKey)) {
                throw new Exception(
                    __("Please check your conekta config.")
                );
            }

            Conekta::setApiKey($privateKey);
            Conekta::setApiVersion($apiVersion);
            Conekta::setPlugin($pluginType);
            Conekta::setPluginVersion($pluginVersion);
            Conekta::setLocale($locale);
        } catch (\Exception $e) {
            throw new Exception(
                __($e->getMessage())
            );
        }
    }
}
