<?php

namespace Conekta\Payments\Model;

use Magento\Framework\Session\Config\ConfigInterface;
use Magento\Framework\Session\SaveHandlerInterface;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\Session\SessionStartChecker;
use Magento\Framework\Session\SidResolverInterface;
use Magento\Framework\Session\StorageInterface;
use Magento\Framework\Session\ValidatorInterface;

/**
 * Class Session
 * @package Conekta\Payments\Model
 */
class Session extends SessionManager
{
    /**
     * Session constructor.
     * @param \Magento\Framework\App\Request\Http $request
     * @param SidResolverInterface $sidResolver
     * @param ConfigInterface $sessionConfig
     * @param SaveHandlerInterface $saveHandler
     * @param ValidatorInterface $validator
     * @param StorageInterface $storage
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory
     * @param \Magento\Framework\App\State $appState
     * @param SessionStartChecker|null $sessionStartChecker
     * @throws \Magento\Framework\Exception\SessionException
     */
    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        SidResolverInterface $sidResolver,
        ConfigInterface $sessionConfig,
        SaveHandlerInterface $saveHandler,
        ValidatorInterface $validator,
        StorageInterface $storage,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Magento\Framework\App\State $appState,
        SessionStartChecker $sessionStartChecker = null
    ) {
        parent::__construct(
            $request,
            $sidResolver,
            $sessionConfig,
            $saveHandler,
            $validator,
            $storage,
            $cookieManager,
            $cookieMetadataFactory,
            $appState,
            $sessionStartChecker
        );
    }

    /**
     * Set Promotion Code
     *
     * @param string|null
     * @return $this
     */
    public function setConektaCheckoutId($url)
    {
        $this->storage->setData('conekta_checkout_id', $url);
        return $this;
    }

    /**
     * Retrieve promotion code from current session
     *
     * @return string|null
     */
    public function getConektaCheckoutId()
    {
        if ($this->storage->getData('conekta_checkout_id')) {
            return $this->storage->getData('conekta_checkout_id');
        }
        return null;
    }
}
