<?php

namespace Conekta\Payments\Controller\ApplePay;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;

class DomainAssociation extends Action implements CsrfAwareActionInterface
{
    /**
     * @var RawFactory
     */
    private RawFactory $resultRawFactory;

    /**
     * @param Context $context
     * @param RawFactory $resultRawFactory
     */
    public function __construct(
        Context $context,
        RawFactory $resultRawFactory
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
    }

    /**
     * Create exception in case CSRF validation failed.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Execute action based on request
     *
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute()
    {
        // Apple Pay domain association file content
        $content = '7b2276657273696f6e223a312c227073704964223a2243353241324536454230304431323336323434354345323746364243453445413534424342363643393339333330344533343842303830333843453343333538222c22637265617465644f6e223a313736323532393837333132307d';
        
        $resultRaw = $this->resultRawFactory->create();
        $resultRaw->setHeader('Content-Type', 'text/plain', true);
        $resultRaw->setContents($content);
        
        return $resultRaw;
    }
}

