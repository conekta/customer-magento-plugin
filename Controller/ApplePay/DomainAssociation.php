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
        $content = '{"version":1,"pspId":"C52A2E6EB00D12362445CE27F6BCE4EA54BCB66C9393304E348B08038CE3C358","createdOn":1762529873120}';
        
        $resultRaw = $this->resultRawFactory->create();
        $resultRaw->setHeader('Content-Type', 'application/json', true);
        $resultRaw->setContents($content);
        
        return $resultRaw;
    }
}

