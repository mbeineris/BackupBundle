<?php

namespace Mabe\BackupBundle\Saver;

interface SaverInterface
{
    /**
     * save
     *
     * @param  mixed $json
     *
     * @return void
     */
    public function save($json, $filename);
}