<?php

namespace FilelibIo\Filelib;

use Xi\Filelib\Command\Command;
use Xi\Filelib\File\File;
use Xi\Filelib\File\FileRepository;
use Xi\Filelib\FileLibrary;
use Xi\Filelib\Plugin\VersionProvider\VersionProvider;
use Xi\Filelib\Profile\ProfileManager;

class RecreateResources implements Command
{
    /**
     * @var File
     */
    private $file;

    /**
     * @var FileRepository
     */
    private $fiop;

    /**
     * @var ProfileManager
     */
    private $pm;

    public function __construct(File $file)
    {
        $this->file = $file;
    }

    public function execute()
    {
        $plugins = $this->pm->getProfile($this->file->getProfile())->getPlugins();
        foreach ($plugins as $plugin) {
            if ($plugin instanceof VersionProvider) {
                if ($plugin->isApplicableTo($this->file)) {
                    try {
                        $plugin->provideAllVersions($this->file);
                    } catch (\Exception $e) {
                        return false;
                    }
                }
            }
        }

        // Version providers may update the file and resource but we must save these wondrous changes by hand.
        return $this->fiop->update($this->file);
    }

    public function attachTo(FileLibrary $filelib)
    {
        $this->fiop = $filelib->getFileRepository();
        $this->pm = $filelib->getProfileManager();
    }

    public function getTopic()
    {
        return 'oy.filelib.command.recreate_resources';
    }

    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->file = $data['file'];
    }

    public function serialize()
    {
        return serialize(
            array(
                'file' => $this->file,
            )
        );
    }

}
