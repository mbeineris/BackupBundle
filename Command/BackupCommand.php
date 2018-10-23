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
        $container = $this->getContainer();
        $em = $this->getContainer()->get('doctrine')->getManager();
        $serializer = $this->getContainer()->get('jms_serializer');

        $registeredEntities = $em->getConfiguration()->getMetadataDriverImpl()->getAllClassNames();
        $now = new \DateTime("now");
        $currentDate = $now->format('YmdHis');

        $configuredEntities = $container->getParameter('mabe_backup.entities');
        ProgressBar::setFormatDefinition('minimal', 'Backup progress: %percent%%');
        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('minimal');
        $progressBar->start();

        $backupSuccess = array();
        foreach ($configuredEntities as $configuredEntity) {
            $entityName = $configuredEntity['model'];
            if (in_array('AppBundle\Entity\\'.$configuredEntity['model'], $registeredEntities)) {

                $backupName = $entityName."_".$currentDate.".json.gz";
                $objects = $em->getRepository('AppBundle:'.$entityName)->findAll();
                $backupJson = $serializer->serialize($objects, 'json', SerializationContext::create()->setGroups($configuredEntity['groups']));
                $backupJson = gzencode($backupJson);

                // Handle local case
                $configuredLocal = $container->getParameter('mabe_backup.local');
                if (!empty($configuredLocal)) {
                    file_put_contents($configuredLocal . $backupName, $backupJson);
                    $progressBar->advance();
                }

                // Handle gaufrette case
                $configuredFilesystems = $container->getParameter('mabe_backup.gaufrette_filesystems');
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
                            $progressBar->advance();
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
