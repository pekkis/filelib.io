<?php

namespace FilelibIo\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Xi\Filelib\Backend\FindByIdsRequest;
use Xi\Filelib\File\File;
use Xi\Filelib\FileLibrary;
use Xi\Filelib\Publisher\Publisher;
use Symfony\Component\Console\Command\Command;
use FilelibIo\Filelib\RecreateResources;

class RecreateFilelibResourcesCommand extends Command
{
    /**
     * @var FileLibrary
     */
    protected $filelib;

    /**
     * @var Publisher
     */
    protected $publisher;

    public function __construct($app)
    {
        parent::__construct();
        $this->app = $app;
        $this->filelib = $app['filelib'];
        $this->publisher = $app['filelib.publisher'];
    }

    protected function configure()
    {
        $this
            ->setName('filelibio:recreate-filelib-resources')
            ->setDescription('Recreates all or some filelib resources')
            ->addOption('files', 'f', InputOption::VALUE_OPTIONAL, 'Files separated by comma')
            ->addOption('mimetype', 'm', InputOption::VALUE_OPTIONAL, 'Filter by mimetype');
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mimeFilter = $input->getOption('mimetype');

        $fileIds = $input->getOption('files');
        if ($fileIds) {

            $fileIds = explode(',', $fileIds);

            // Yep we must go very deep here, and we shouldn't have to :)
            // But the finders dont support this operation yet, made an issue.
            $files = $this->filelib
                ->getBackend()
                ->getBackendAdapter()
                ->findByIds(new FindByIdsRequest($fileIds, 'Xi\Filelib\File\File'))
                ->getResult();

        } else {
            $files = $this->filelib->getFileRepository()->findAll();
        }

        foreach ($files as $file) {

            /** @var File $file */

            if ($mimeFilter) {
                if (!preg_match("#^{$mimeFilter}#", $file->getMimeType())) {
                    continue;
                }
            }

            $output->writeln(
                sprintf("Enqueueing file #%s", $file->getId())
            );

            $command = new RecreateResources($file);

            $this->filelib->getQueue()->enqueue($command->getTopic(), $command);

        }
    }
}
