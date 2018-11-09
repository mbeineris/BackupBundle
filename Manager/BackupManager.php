<?php

namespace Mabe\BackupBundle\Manager;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Mabe\BackupBundle\Event\BackupEvent;
use JMS\Serializer\SerializerBuilder;


class BackupManager
{
    const BULK_SELECT = 2;
    const MAX_MEMORY = 128;

    private $jobs;
    private $eventDispatcher;
    private $entityManager;

    /**
     * __construct
     *
     * @param  mixed $jobs
     * @param  mixed $eventDispatcher
     *
     * @return void
     */
    public function __construct(array $jobs, EventDispatcherInterface $eventDispatcher, EntityManagerInterface $entityManager)
    {
        $this->jobs = $jobs;
        $this->eventDispatcher = $eventDispatcher;
        $this->entityManager = $entityManager;
    }
    
    /**
     * backup
     *
     * @return void
     */
    public function backup($argumentJobs)
    {
        $serializer = SerializerBuilder::create()->build();
        $registeredEntities = $this->entityManager->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();

        // Set helper options and instantiate variables
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);
        $now = new \DateTime("now");
        $currentDate = $now->format('YmdHis');
        $backedUp = array();
        $completedJobs = array();
        $part = 0;

        foreach ($this->jobs as $jobName => $configuredJob) {

            // Skip jobs that were not in given arguments
            if(!empty($argumentJobs && !in_array($jobName, $argumentJobs))) {
                continue;
            }

            if (!empty($configuredJob['local'])) {
                $localPath = $configuredJob['local'];
            }

            if (!empty($configuredJob['gaufrette'])) {
                $configuredFilesystems = $configuredJob['gaufrette'];
            }

            foreach ($configuredJob['entities'] as $entity => $entityOptions) {

                $entityName = substr($entity, strrpos($entity, "\\") + 1);
                $bundleName = strtok($entity, "\\");
                $entityAssociations = $this->entityManager->getClassMetadata($entity)->getAssociationNames();

                if (in_array($entity, $registeredEntities)) {

                    $backupName = "[".$jobName."][".lcfirst($entityName)."]_".$currentDate.".json.gz";
                    $count =  $q = $this->entityManager->createQuery('select count(e.id) from '.$bundleName.':'.$entityName.' e')->getSingleScalarResult();
                    $timesToQuery = floor($count/self::BULK_SELECT);
                    //TODO: Remainder select
                    $remainder = $count % self::BULK_SELECT;
                    $backupJson = '';
                    if($timesToQuery > 0) {

                        for ($i = 0; $i < $timesToQuery; $i++) {
                            
                            $start = self::BULK_SELECT*$i;
                            $q = $this->entityManager->createQuery('select e from '.$bundleName.':'.$entityName.' e');
                            $q->setFirstResult($start);
                            $q->setMaxResults(self::BULK_SELECT);
                            $iterableResult = $q->iterate();

                            foreach ($iterableResult as $row) {

                                // Object of the entity
                                $object = $row[0];
        
                                // Dispatch preBackupEvent
                                $event = new BackupEvent();
                                $event->setObject($object);
                                $event->setActiveJob($jobName);
                                $this->eventDispatcher->dispatch(BackupEvent::PRE_BACKUP, $event);
        
                                if(!$event->getSerialize()) {
                                    continue;
                                }
        
                                // If groups specified
                                if(!empty($entityOptions['groups'])) {
                                    $json = $serializer->serialize($object, 'json', SerializationContext::create()->setGroups($entityOptions['groups']));
                                } else {
                                    $serializeObject = new \stdClass();
                                    foreach ($this->entityManager->getClassMetadata($entity)->getReflectionClass()->getProperties() as $reflectionProperty) {
        
                                        $property = $reflectionProperty->name;
                                        // If properties specified
                                        if (!empty($configuredProps = $entityOptions['properties'])) {
                                            if(in_array($property, $configuredProps)){
                                                if (in_array($property, $entityAssociations) ){
                                                    $serializeObject->$property = array('id' => $object->getId());
                                                } else {
                                                    $serializeObject->$property = $object->{'get'.$property}();
                                                }
                                            }
                                        } else {
                                            // If property is association, backup only id
                                            if (in_array($property, $entityAssociations)){
                                                $serializeObject->$property = array('id' => $object->getId());
                                            } else {
                                                $serializeObject->$property = $object->{'get'.$property}();
                                            }
                                        }
                                    }
                                    $json = $serializer->serialize($serializeObject, 'json');
                                }
        
                                $event = new BackupEvent();
                                $this->eventDispatcher->dispatch(BackupEvent::POST_BACKUP, $event);
        
                                $this->entityManager->detach($object);
                                $backupJson .= $json;
                            }
                            // In mbs
                            if (memory_get_usage(true) / 1000000 > self::MAX_MEMORY && !empty($localPath)) {
                                $part++;
                                $this->localBackup($part, $localPath, $backupName, $backupJson);
                                $backupJson = '';
                                if (memory_get_usage(true) / 1000000 > self::MAX_MEMORY) {
                                    die('Out of memory. Either increase php_memory_limit or change configuration.');
                                }
                            }
                        }
                    }

                    // Handle local backup
                    if (!empty($localPath)) {
                        $this->localBackup($part, $localPath, $backupName, $backupJson);
                    }

                    // Handle gaufrette case
                    if (!empty($configuredFilesystems)) {

                        foreach ($configuredFilesystems as $filesystemName) {
                            try {
                                $filesystem = $container->get('knp_gaufrette.filesystem_map')->get($filesystemName);
                            } catch (\Exception $e) {
                                break;
                            }
                            if (!$filesystem->has($backupName)) {
                                $filesystem->write($entityName . "_" . $backupName, $backupJson);
                            }
                        }
                    }
                    array_push($backedUp, $entityName);
                    array_push($completedJobs, $jobName);

                }
                gc_collect_cycles();
                $part = 0;
            }
        }

        // Dispatch postBackup
        $event = new BackupEvent();
        $event->setJobs($completedJobs);
        $this->eventDispatcher->dispatch(BackupEvent::POST_BACKUP, $event);

        return $backedUp;
    }

    /**
     * createFolders
     *
     * @return void
     */
    public function createFolders()
    {
        return $this->jobs;
    }

    /**
     * validateArgumentJobs
     *
     * @param  mixed $argumentJobs
     *
     * @return void
     */
    public function validateJobs($argumentJobs)
    {
        return array_diff($argumentJobs, array_keys($this->jobs));
    }

    /**
     * getJobs
     *
     * @return void
     */
    public function getJobs()
    {
        return $this->jobs;
    }

    /**
     * localBackup
     *
     * @param  mixed $part
     * @param  mixed $path
     * @param  mixed $name
     * @param  mixed $json
     *
     * @return void
     */
    public function localBackup($part, $path, $name, $json)
    {
        $backupJson = gzencode($json);
        $fileName = $path . $part>0 ? "[".$part."]" :"" .$name;
        file_put_contents($path.$fileName, $backupJson);
    }
}