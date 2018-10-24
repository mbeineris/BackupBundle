<?php

namespace Mabe\BackupBundle\Command;

use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BackupCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('mabe:backup')
            ->setDescription('Makes a backup of configured entities in JSON format.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get needed services
        $container = $this->getContainer();
        $em = $container->get('doctrine')->getManager();
        $serializer = $container->get('jms_serializer');

        // Set helper options and instantiate variables
        $em->getConnection()->getConfiguration()->setSQLLogger(null);
        $registeredEntities = $em->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
        $now = new \DateTime("now");
        $currentDate = $now->format('YmdHis');
        $backupSuccess = array();

        // Get configuration
        $configuredJobs = $container->getParameter('mabe_backup.jobs');


        // Initiate progress bar
        ProgressBar::setFormatDefinition('memory', 'Using %memory% of memory.');
        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('memory');
        $progressBar->start();


        foreach ($configuredJobs as $configuredJob) {

            foreach ($configuredJob['entities'] as $entity => $entityOptions) {

                $entityName = substr($entity, strrpos($entity, "\\") + 1);
                $bundleName = strtok($entity, "\\");

                if (in_array($entity, $registeredEntities)) {

                    $backupName = $entityName."_".$currentDate.".json.gz";

                    $q = $em->createQuery('select u from '.$bundleName.':'.$entityName.' u');
                    $iterableResult = $q->iterate();
                    $backupJson = '';
                    foreach ($iterableResult as $row) {
                        $json = $serializer->serialize($row[0], 'json', SerializationContext::create()->setGroups($entityOptions['groups']));
                        $backupJson .= $json;
                        $progressBar->advance();
                        $em->detach($row[0]);
                    }
                    $backupJson = gzencode($backupJson);

                    // Handle local case
                    if (!empty($configuredJob['local'])) {
                        file_put_contents($configuredJob['local'] . $backupName, $backupJson);
                    }

                    // Handle gaufrette case
                    if (!empty($configuredFilesystems = $configuredJob['gaufrette'])) {
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
}
