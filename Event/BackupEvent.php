<?php

namespace Mabe\BackupBundle\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class BackupEvent
 * @package Mabe\BackupBundle\Event
 */
class BackupEvent extends Event
{
    const PRE_BACKUP = 'mabe.event.pre_backup';
    const POST_BACKUP = 'mabe.event.post_backup';

    protected $object;
    protected $jobs;
    protected $activeJob;

    /**
     * @param mixed $object
     */
    public function setObject($object)
    {
        $this->object = $object;
    }

    /**
     * @return mixed
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param array $jobs
     */
    public function setJobs(array $jobs)
    {
        $this->jobs = $jobs;
    }

    /**
     * @return mixed
     */
    public function getJobs()
    {
        return $this->jobs;
    }

    /**
     * @param $activeJob
     */
    public function setActiveJob($activeJob)
    {
        $this->activeJob = $activeJob;
    }

    /**
     * @return mixed
     */
    public function getActiveJob()
    {
        return $this->activeJob;
    }
}