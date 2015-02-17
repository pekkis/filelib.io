<?php

namespace FilelibIo\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use Xi\Filelib\FileLibrary;
use Xi\Filelib\Publisher\Publisher;

class PublishFilesCommand extends Command
{
    private $app;

    public function __construct($app)
    {
        parent::__construct();
        $this->app = $app;
    }

    protected function configure()
    {
        $this
            ->setName('filelibio:publish-files')
            ->setDescription('Publishes all files')
            ;
        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $session = $this->app['session'];
        $session->set('user', 'consoleuser');

        /** @var FileLibrary $filelib */
        $filelib = $this->app['filelib'];

        /** @var Publisher $publisher */
        $publisher = $this->app['filelib.publisher'];

        foreach ($filelib->getFileRepository()->findAll() as $file) {
            $publisher->unpublishAllVersions($file);
            $publisher->publishAllVersions($file);
        }
    }
}
