<?php

namespace Drivingschool\Command;

use Pekkis\Queue\SymfonyBridge\ConsoleOutputSubscriber as QueueOutputSubscriber;
use Pekkis\Queue\Processor\ConsoleOutputSubscriber as ProcessorOutputSubscriber;
use Pekkis\Queue\SymfonyBridge\EventDispatchingQueue;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Pekkis\Queue\Processor\Processor;
use Xi\Filelib\FilelibException;
use DateTime;
use Xi\Filelib\Queue\FilelibMessageHandler;
use Symfony\Component\Console\Command\Command;

/**
 */
class QueueProcessorCommand extends Command
{
    protected $app;

    public function __construct($app)
    {
        parent::__construct();
        $this->app = $app;
        $this->queue = $app['filelib']->getQueue();
    }

    protected function configure()
    {
        $this
            ->setName('filelibio:queue-processor')
            ->setDescription('Processes filelib queue');
    }

    /**
     * @var EventDispatchingQueue
     */
    private $queue;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->queue->addSubscriber(new QueueOutputSubscriber($output));
        $this->queue->addSubscriber(new ProcessorOutputSubscriber($output));

        $processor = new Processor(
            $this->queue
        );
        $processor->registerHandler(new FilelibMessageHandler());

        $processor->processWhile(
            function ($gotMessage) {
                return $gotMessage;
            }
        );
    }
}
