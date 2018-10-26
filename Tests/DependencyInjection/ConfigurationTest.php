<?php

namespace Mabe\BackupBundle\Tests\DependencyInjection;


use Mabe\BackupBundle\DependencyInjection\Configuration;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * ConfigurationTest
 */
class ConfigurationTest extends WebTestCase
{
    /**
     * @var Processor
     */
    private $processor;

    public function setUp()
    {
        $this->processor = new Processor();
    }

    /**
     * @param array $configArray
     * @return array
     */
    private function getConfigs(array $configArray)
    {
        $configuration = new Configuration();
        return $this->processor->processConfiguration($configuration, array($configArray));
    }

    public function testReservedWordConfiguration()
    {
        $jobs = array('job1' => array(), 'job2' => array('entities' => array('AppBundle\\Entity\\Test' => array())), 'jobs' => array());
        $this->expectException(InvalidConfigurationException::class);
        $this->getConfigs(array('jobs' => $jobs));
    }

    public function testEmptyJobsConfiguration()
    {
        $jobs = array();
        $this->expectException(InvalidConfigurationException::class);
        $this->getConfigs(array('jobs' => $jobs));
    }

    public function testLocalConfiguration()
    {
        $localDir = '/home/username/backups';
        $configuration = $this->getConfigs(array('jobs' => array(array('local' => $localDir))));
        $this->assertArrayHasKey('local', $configuration['jobs'][0]);
        $this->assertEquals($configuration['jobs'][0]['local'], '/home/username/backups/');
    }

    public function testGaufretteConfiguration()
    {
        $filesystems = array('test1_fs', 'test2_fs');
        $configuration = $this->getConfigs(array('jobs' => array(array('gaufrette' => $filesystems))));
        $this->assertArrayHasKey('gaufrette', $configuration['jobs'][0]);
        $this->assertEquals($filesystems, $configuration['jobs'][0]['gaufrette']);
    }

    public function testValidSaveLocationConfiguration()
    {
        $jobs = array(
            'job1' => array(
                'local' => '/project/backup',
                'gaufrette' => array('backup_fs')
            ),
            'job2' => array(
                'local' => '/project/backup'
            ),
            'job3' => array(
                'gaufrette' => array('backup_fs')
            ),
        );
        $this->getConfigs(array('jobs' => $jobs));
    }

    public function testInvalidSaveLocation()
    {
        $jobs = array('job1' => array());
        $this->expectException(InvalidConfigurationException::class);
        $this->getConfigs(array('jobs' => $jobs));
    }
}