<?php

namespace Mabe\BackupBundle\Command;

use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BackupCommand extends ContainerAwareCommand
{
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
        $serializer = $container->get('jms_serializer');

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

            foreach ($configuredJob['entities'] as $entity => $entityOptions) {

                $entityName = substr($entity, strrpos($entity, "\\") + 1);
                $bundleName = strtok($entity, "\\");

                if (in_array($entity, $registeredEntities)) {

                    $backupName = $entityName."_".$currentDate.".json.gz";

                    $q = $em->createQuery('select e from '.$bundleName.':'.$entityName.' e');
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
}
