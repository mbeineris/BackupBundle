<?php

namespace Mabe\BackupBundle\Util;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Mabe\BackupBundle\Event\BackupEvent;
use JMS\Serializer\SerializerBuilder;
use Mabe\BackupBundle\Saver\GaufretteSaver;


class BackupManager
{
    const BULK_SELECT = 700;
    const MEMORY_OVERHEAD = 10;

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
    public function __construct(EventDispatcherInterface $eventDispatcher, EntityManagerInterface $entityManager)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->entityManager = $entityManager;
    }
    
    /**
     * backup
     *
     * @return void
     */
    public function backup($job, $jobName, $saver)
    {
        $serializer = SerializerBuilder::create()->build();
        $registeredEntities = $this->entityManager->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();

        // Set helper options and instantiate variables
        $maxMemory = ini_get('memory_limit');
        if ($maxMemory != -1 && is_string($maxMemory)){
            $maxMemory = rtrim($maxMemory, "M ");
            $maxMemory -= self::MEMORY_OVERHEAD;
        }
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);
        $now = new \DateTime("now");
        $currentDate = $now->format('YmdHis');
        $part = 0; $objectIteration = 0;

        foreach ($job['entities'] as $entity => $entityOptions) {

            $entityName = substr($entity, strrpos($entity, "\\") + 1);
            $bundleName = strtok($entity, "\\");
            $entityAssociations = $this->entityManager->getClassMetadata($entity)->getAssociationNames();
            $saveStatus = null;

            if (in_array($entity, $registeredEntities)) {

                $backupName = "[".$jobName."][".lcfirst($entityName)."]_".$currentDate.".json.gz";
                $count =  $q = $this->entityManager->createQuery('select count(e.id) from '.$bundleName.':'.$entityName.' e')->getSingleScalarResult();
                $timesToQuery = floor($count/self::BULK_SELECT);
                $backupJson = '';
                if($timesToQuery > 0 || $count % self::BULK_SELECT > 0) {
                    // Loop 1 more time at the end to get the remainder
                    for ($i = 0; $i <= $timesToQuery; $i++) {
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

                            // Save file and release memory ..
                            if (
                                // Running out of memory
                                (memory_get_usage(true) / 1000000 > $maxMemory && $maxMemory != -1) 
                                // Or batches are configured
                                || (!empty($entityOptions['batch']) && $objectIteration !== 0 && $objectIteration % $entityOptions['batch'] === 0)
                            ) {
                                $part++;
                                $this->save($part, $backupName, $backupJson, $entityName, $saver);
                                $backupJson = '';
                                if (memory_get_usage(true) / 1000000 > $maxMemory && $maxMemory != -1) {
                                    die('Out of memory. Either increase php_memory_limit or change configuration.');
                                }
                            }

                            $backupJson .= $json.',';

                            $event = new BackupEvent();
                            $this->eventDispatcher->dispatch(BackupEvent::POST_BACKUP, $event);
    
                            $this->entityManager->detach($object);
                            $objectIteration++;
                        }
                    }
                }
                // Save
                if ($objectIteration !== $part) {
                    $this->save($part, $backupName, $backupJson, $entityName, $saver);
                }
                
            }
            gc_collect_cycles();
            $part = 0;
            $objectIteration = 0;
        }

        // Dispatch postBackup
        $event = new BackupEvent();
        $this->eventDispatcher->dispatch(BackupEvent::POST_BACKUP, $event);

        return $jobName;
    }

    private function save($part, $backupName, $backupJson, $entityName, $saver)
    {
        $filename = $part>0 ? "[".$part."]" :"" .$backupName;
        // Make a valid compressed json
        $backupJson = gzencode('{"'.$entityName.'":['.rtrim($backupJson, ', ').']}');
        // Save with service
        $saver->save($backupJson, $filename);
    }
}