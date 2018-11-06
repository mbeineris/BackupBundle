<?php

namespace Mabe\BackupBundle\Command;


use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerBuilder;
use Mabe\BackupBundle\Event\BackupEvent;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BackupCommand extends ContainerAwareCommand
{
    const MAX_MEMORY = 128;
    const BULK_SELECT = 700;

    protected function configure()
    {
        $this
            ->setName('mabe:backup')
            ->setDescription('Makes a backup of configured entities in JSON format.')
            ->setHelp("") // "--help" option
            ->addArgument(
                'jobs',
                InputArgument::IS_ARRAY,
                'Runs specified(by name) jobs. If omitted, runs all.'
            )
            ->addOption(
                'list',
                null,
                InputOption::VALUE_OPTIONAL,
                'Lists all configured jobs.',
                false
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get passed arguments and options
        $jobs = $input->getArgument('jobs');
        $option = $input->getOption('list');
        $list = ($option !== false);

        // Get needed services
        $container = $this->getContainer();
        $em = $container->get('doctrine')->getManager();
        $serializer = SerializerBuilder::create()->build();
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $output->writeln('Symfony BackupBundle by Marius Beineris and contributors.');
        $output->writeln('');

        // Get configuration
        $configuredJobs = $container->getParameter('mabe_backup.jobs');

        // List configured jobs
        if (!empty($list)) {
            $this->listJobs($output, $configuredJobs);
            // If no arguments passed, stop command
            if (empty($jobs)) {
                return;
            }
        }

        // Set helper options and instantiate variables
        $em->getConnection()->getConfiguration()->setSQLLogger(null);
        $registeredEntities = $em->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
        $now = new \DateTime("now");
        $currentDate = $now->format('YmdHis');
        $backupSuccess = array();
        $completedJobs = array();
        $part = 0;

        // If arguments given, check if they are valid
        if (!empty($jobs)) {
            $unknownJobs = array_diff($jobs, array_keys($configuredJobs));
            if (!empty($unknownJobs)) {
                $output->write('Unknown Job(s): ');
                $output->writeln(implode(', ', $unknownJobs));
                $output->writeln('');
                // Check if list was not displayed
                if(empty($list)) {
                    $this->listJobs($output, $configuredJobs);
                }
                return;
            }
        }

        // Initiate progress bar
        ProgressBar::setFormatDefinition('memory', 'Using %memory% of memory.');
        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('memory');
        $progressBar->start();

        foreach ($configuredJobs as $jobName => $configuredJob) {

            // Skip jobs that were not in given arguments
            if(!empty($jobs && !in_array($jobName, $jobs))) {
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
                $entityAssociations = $em->getClassMetadata($entity)->getAssociationNames();

                if (in_array($entity, $registeredEntities)) {

                    $backupName = "[".$jobName."][".$entityName."]_".$currentDate.".json.gz";
                    $count =  $q = $em->createQuery('select count(e.id) from '.$bundleName.':'.$entityName.' e')->getSingleScalarResult();
                    $timesToQuery = floor($count/self::BULK_SELECT);
                    //TODO: Remainder select
                    $remainder = $count % self::BULK_SELECT;
                    $backupJson = '';
                    if($timesToQuery > 0) {

                        for ($i = 0; $i < $timesToQuery; $i++) {

                            $start = self::BULK_SELECT*$i;
                            $q = $em->createQuery('select e from '.$bundleName.':'.$entityName.' e');
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
                                $dispatcher->dispatch(BackupEvent::PRE_BACKUP, $event);

                                if(!$event->getSerialize()) {
                                    continue;
                                }

                                // If groups specified
                                if(!empty($entityOptions['groups'])) {
                                    $json = $serializer->serialize($object, 'json', SerializationContext::create()->setGroups($entityOptions['groups']));
                                } else {
                                    $serializeObject = new \stdClass();
                                    foreach ($em->getClassMetadata($entity)->getReflectionClass()->getProperties() as $reflectionProperty) {

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
                                $dispatcher->dispatch(BackupEvent::POST_BACKUP, $event);

                                $em->detach($object);
                                $backupJson .= $json;
                                $progressBar->advance();

                            }

                            if (memory_get_usage(true) / 1000000 > self::MAX_MEMORY && !empty($localPath)) {
                                $part++;
                                $this->localBackup($part, $localPath, $backupName, $backupJson);
                                $backupJson = '';
                                if (memory_get_usage(true) / 1000000 > self::MAX_MEMORY) {
                                    $output->writeln('');
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
                                $progressBar->clear();
                                $output->writeln($filesystemName. " filesystem for entity ".$entityName." not found.");
                                $progressBar->display();
                                break;
                            }

                            if (!$filesystem->has($backupName)) {
                                $filesystem->write($entityName . "_" . $backupName, $backupJson);
                            } else {
                                $progressBar->clear();
                                $output->writeln($backupName. " already exists.");
                                $progressBar->display();
                            }
                        }
                    }
                    array_push($backupSuccess, $entityName);
                    array_push($completedJobs, $jobName);

                    // Dispatch postBackup
                    $event = new BackupEvent();
                    $event->setJobs($completedJobs);
                    $dispatcher->dispatch(BackupEvent::POST_BACKUP, $event);
                    $part = 0;

                } else {
                    $progressBar->clear();
                    $output->writeln($entityName. " entity not found.");
                    $progressBar->display();
                }
                gc_collect_cycles();

            }

        }
        sleep(1);
        $progressBar->finish();
        $output->writeln('');
        if (!empty($backupSuccess)) {
            $output->writeln('Successfully backed entities: '. implode(", ", $backupSuccess));
            $output->writeln('');
        } else {
            $output->writeln('All backups has failed.');
            $output->writeln('');
        }
    }

    public function listJobs(OutputInterface $output, $configuredJobs)
    {
        $table = new Table($output);
        $output->writeln('Backup Jobs:');
        $table
            ->setHeaders(array('#', 'Job Name', 'Entities', 'Local Dir', 'Filesystem'));
        $i = 0;
        foreach ($configuredJobs as $jobName => $job) {
            // array("AppBundle\Entity\Test1", "AppBundle\Entity\Test2") -> array("Test1", "Test2")
            $entities = array_map(function ($x){ return substr($x, strrpos($x, "\\") + 1); }, array_keys($job['entities']));
            $table->addRow(array($i, $jobName, implode(', ', $entities), $job['local'], implode(', ', $job['gaufrette'])));
            $i++;
        }
        $table->setStyle('borderless');
        $table->render();
        $output->writeln('');
    }

    public function localBackup($part, $path, $name, $json)
    {
        $backupJson = gzencode($json);
        file_put_contents($path . "[".$part."]".$name, $backupJson);
    }
}
