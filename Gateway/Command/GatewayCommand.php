<?php
namespace Conekta\Payments\Gateway\Command;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\Phrase;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;

class GatewayCommand implements CommandInterface
{
    /**
     * @var BuilderInterface
     */
    private $requestBuilder;

    /**
     * @var TransferFactoryInterface
     */
    private $transferFactory;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var HandlerInterface
     */
    private $handler;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var ConektaLogger
     */
    private $_conektaLogger;

    /**
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param ConektaLogger $conektaLogger
     * @param HandlerInterface|null $handler
     * @param ValidatorInterface|null $validator
     */
    public function __construct(
        BuilderInterface $requestBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $client,
        ConektaLogger $conektaLogger,
        ?HandlerInterface $handler = null,
        ?ValidatorInterface $validator = null
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->transferFactory = $transferFactory;
        $this->client = $client;
        $this->handler = $handler;
        $this->validator = $validator;
        $this->_conektaLogger = $conektaLogger;
        $this->_conektaLogger->info('Command GatewayCommand :: __construct');
    }

    /**
     * Execute
     *
     * @param array $commandSubject
     * @return void
     * @throws CommandException
     * @throws ClientException
     * @throws ConverterException
     */
    public function execute(array $commandSubject)
    {
        $this->_conektaLogger->info('Command GatewayCommand :: execute');

        // @TODO implement exceptions catching
        $transferO = $this->transferFactory->create(
            $this->requestBuilder->build($commandSubject)
        );
        $response = $this->client->placeRequest($transferO);
        if ($this->validator !== null) {
            $result = $this->validator->validate(
                array_merge($commandSubject, ['response' => $response])
            );
            if (!$result->isValid()) {
                $this->logExceptions($result->getFailsDescription());

                $errorMessages = [];
                foreach ($result->getFailsDescription() as $failPhrase) {
                    $errorMessages[] = (string)$failPhrase;
                }

                throw new CommandException(
                    __(implode("; ", $errorMessages))
                );
            }
        }

        if ($this->handler) {
            $this->handler->handle(
                $commandSubject,
                $response
            );
        }
    }

    /**
     * Log Exceptions
     *
     * @param Phrase[] $fails
     * @return void
     */
    private function logExceptions(array $fails)
    {
        foreach ($fails as $failPhrase) {
            $this->_conektaLogger->critical((string) $failPhrase);
        }
    }
}
