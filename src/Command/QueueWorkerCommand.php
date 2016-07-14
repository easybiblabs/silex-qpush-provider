<?php

namespace EasyBib\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Uecode\Bundle\QPushBundle\Event\Events;
use Uecode\Bundle\QPushBundle\Event\MessageEvent;
use Uecode\Bundle\QPushBundle\Message\Message;
use Uecode\Bundle\QPushBundle\Provider\AwsProvider;
use Uecode\Bundle\QPushBundle\Provider\ProviderRegistry;

class QueueWorkerCommand extends Command
{
    /** @var ProviderRegistry */
    private $registry;

    /** @var OutputInterface */
    private $output;

    /** @var EventDispatcherInterface */
    private $dispatcher;

    public function __construct(ProviderRegistry $registry, EventDispatcherInterface $dispatcher)
    {
        parent::__construct();

        $this->registry = $registry;
        $this->dispatcher = $dispatcher;
    }

    protected function configure()
    {
        $this
            ->setName('uecode:qpush:worker')
            ->setDescription('Polls the configured Queues')
            ->addArgument('name', InputArgument::REQUIRED, 'Name of a specific queue to poll', null)
            ->addOption('messages', 'm', InputOption::VALUE_OPTIONAL, 'Number of messages that should be processed before exit (default unlimited)')
            ->addOption('sleep', 's', InputOption::VALUE_OPTIONAL, 'Sleep time in sec between polling (default none)', 0)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $name = $input->getArgument('name');
        $processMessageLimit = $input->getOption('messages');
        $sleep = $input->getOption('sleep');

        if (!$this->registry->has($name)) {
            return $this->output->writeln(
                sprintf('<error>The [%s] queue you have specified does not exists!</error>', $name)
            );
        }

        $messageCounter = 0;
        while ($processMessageLimit === null || $processMessageLimit >= $messageCounter) {
            $messageCounter += $this->pollQueue($name);

            if ($sleep) {
                sleep($sleep);
            }
        }
    }

    private function pollQueue($name)
    {
        $provider = $this->registry->get($name);
        /** @var Message[] $messages */
        $messages = $provider->receive();
        foreach ($messages as $message) {
            $messageEvent = new MessageEvent($name, $message);
            $this->dispatcher->dispatch(Events::Message($name), $messageEvent);
            if ($provider instanceof AwsProvider) {
                $provider->delete($message->getMetadata()->get('ReceiptHandle'));
            }
        }

        $msg = '<info>Finished polling %s Queue, %d messages fetched.</info>';
        $this->output->writeln(sprintf($msg, $name, sizeof($messages)));

        return count($messages);
    }
}
