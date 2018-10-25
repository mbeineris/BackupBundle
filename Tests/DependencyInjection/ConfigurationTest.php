<?php

namespace Mabe\BackupBundle\Tests\DependencyInjection;


use Mabe\BackupBundle\DependencyInjection\Configuration;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Config\Definition\Processor;

if (!class_exists('\PHPUnit_Framework_TestCase') &&
    class_exists('\PHPUnit\Framework\TestCase')) {
    class_alias('\PHPUnit\Framework\TestCase', '\PHPUnit_Framework_TestCase');
}
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
}