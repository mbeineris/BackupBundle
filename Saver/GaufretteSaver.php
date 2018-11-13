<?php

namespace Mabe\BackupBundle\Saver;

class GaufretteSaver implements SaverInterface
{
    private $filesystemName;
    private $gaufrette;

    /**
     * __construct
     *
     * @param  mixed $filesystemName
     * @param  mixed $gaufrette
     *
     * @return void
     */
    public function __construct($filesystemName, $gaufrette)
    {
        $this->gaufrette = $gaufrette;
        $this->filesystemName = $filesystemName;
    }

    /**
     * save
     *
     * @param  mixed $json
     * @param  mixed $filename
     *
     * @return void
     */
    public function save($json, $filename)
    {
        if (empty($this->filesystemName)) {
            throw new \Exception('Filesystem not specified.');
        }

        try {
            $filesystem = $this->gaufrette->get($this->filesystemName);
        } catch(\Exception $e) {
            throw $e;
        }

        if (!$filesystem->has($filename)) {
            $filesystem->write($filename, $json);
        }
    }
}