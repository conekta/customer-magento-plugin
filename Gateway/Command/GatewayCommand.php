<?php
namespace Conekta\Payments\Gateway\Command;

use Conekta\Payments\Logger\Logger as ConektaLogger;
use Magento\Framework\Phrase;
use Magento\Payment\Gateway\Command\CommandException;
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
    private BuilderInterface $requestBuilder;

    /**
     * @var TransferFactoryInterface
     */
    private TransferFactoryInterface $transferFactory;

    /**
     * @var ClientInterface
     */
    private ClientInterface $client;

    /**
     * @var ?HandlerInterface
     */
    private ?HandlerInterface $handler;

    /**
     * @var ?ValidatorInterface
     */
    private ?ValidatorInterface $validator;

    /**
     * @var ConektaLogger
     */
    private ConektaLogger $_conektaLogger;

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
    public function execute(array $commandSubject): void
    {
        $this->_conektaLogger->info('Conekta Command GatewayCommand :: execute');

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
                    !empty($errorMessages)
                        ? __(implode(PHP_EOL, $errorMessages))
                        : __('Transaction has been declined. Please try again later.')
                );
            }
        }

        $this->handler?->handle(
            $commandSubject,
            $response
        );
    }

    /**
     * Log Exceptions
     *
     * @param Phrase[] $fails
     * @return void
     */
    private function logExceptions(array $fails): void
    {
        foreach ($fails as $failPhrase) {
            $this->_conektaLogger->error($failPhrase, $fails);
        }
    }
}
