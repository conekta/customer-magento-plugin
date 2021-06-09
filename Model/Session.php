<?php

namespace Conekta\Payments\Model;

use Magento\Framework\Session\Config\ConfigInterface;
use Magento\Framework\Session\SaveHandlerInterface;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\Session\SessionStartChecker;
use Magento\Framework\Session\SidResolverInterface;
use Magento\Framework\Session\StorageInterface;
use Magento\Framework\Session\ValidatorInterface;

class Session extends SessionManager
{

    protected $storage;
    /**
     * Session constructor.
     * @param StorageInterface $storage
     */
    public function __construct(
        StorageInterface $storage
    ) {
        $this->storage = $storage;
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
