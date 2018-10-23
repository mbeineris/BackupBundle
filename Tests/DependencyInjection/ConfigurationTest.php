<?php

namespace Mabe\BackupBundle\Tests\DependencyInjection;


use Mabe\BackupBundle\DependencyInjection\Configuration;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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

    public function testEntitiesConfiguration()
    {
        $entities = array(array('model' => 'User', 'groups' => array('test_group1', 'test_group1')));
        $configuration = $this->getConfigs(array('entities' => $entities));
        $this->assertArrayHasKey('entities', $configuration);
        $this->assertEquals($entities, $configuration['entities']);
    }

    public function testLocalConfiguration()
    {
        $localDir = '/home/username/backups';
        $configuration = $this->getConfigs(array('local' => $localDir));
        $this->assertArrayHasKey('local', $configuration);
        $this->assertEquals($configuration['local'], '/home/username/backups/');
    }

    public function testGaufretteConfiguration()
    {
        $filesystems = array('test1_fs', 'test2_fs');
        $configuration = $this->getConfigs(array('gaufrette' => $filesystems));
        $this->assertArrayHasKey('gaufrette', $configuration);
        $this->assertEquals($filesystems, $configuration['gaufrette']);
    }
}