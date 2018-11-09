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
use Mabe\BackupBundle\Manager\BackupManager;

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

        $output->writeln('Symfony BackupBundle by Marius Beineris and contributors.');
        $output->writeln('');

        // Get backup manager
        $backupManager = $this->getContainer()->get('mabe.backup.manager');

        // If arguments given, check if they are valid
        if (!empty($jobs)) {
            $unknownJobs = $backupManager->validateJobs($jobs);
            if (!empty($unknownJobs)) {
                $output->write('Unknown Job(s): ');
                $output->writeln(implode(', ', $unknownJobs));
                $output->writeln('');
                // Check if list was not displayed
                if(empty($list)) {
                    $this->listJobs($output, $backupManager->getJobs());
                }
                return;
            }
        }

        // List configured jobs
        if (!empty($list)) {
            $this->listJobs($output, $backupManager->getJobs());
            // If no arguments passed, stop command
            if (empty($jobs)) {
                return;
            }
        }

        $output->writeln('In progress... (NOTE: Depending on your database size and configuration this may take some time.)');
        sleep(1);

        // Start backup
        $result = $backupManager->backup($jobs);

        $output->writeln('');
        if (!empty($result)) {
            $output->writeln('Successfully backed up entities: ['. implode(", ", $result).']');
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
