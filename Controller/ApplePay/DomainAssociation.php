<?php

namespace Conekta\Payments\Controller\ApplePay;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;

class DomainAssociation implements HttpGetActionInterface
{
    /**
     * @var ResponseInterface
     */
    private ResponseInterface $response;

    /**
     * @param ResponseInterface $response
     */
    public function __construct(
        ResponseInterface $response
    ) {
        $this->response = $response;
    }

    /**
     * Execute action based on request
     *
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
        // Apple Pay domain association file content
        $content = '{"version":1,"pspId":"C52A2E6EB00D12362445CE27F6BCE4EA54BCB66C9393304E348B08038CE3C358","createdOn":1762529873120}';
        
        $this->response->setHeader('Content-Type', 'application/json', true);
        $this->response->setBody($content);
        
        return $this->response;
    }
}

