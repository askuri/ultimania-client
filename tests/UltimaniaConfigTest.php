<?php


use PHPUnit\Framework\TestCase;

class UltimaniaConfigTest extends TestCase {

    /**
     * @var UltimaniaConfig
     */
    private $config;

    public function setUp() {
        getcwd(); // if i remove this, the working directory is not set properly. wtf?
        $this->config = UltimaniaConfig::instantiateFromFile('../src/ultimania.xml');
    }

    public function testInstantiateFromFileWorks() {
        // assert options that cannot be set from the xml are set
        $this->assertNotEmpty($this->config->getRequestTimeout());

        // assert options that are set from the xml are set
        $this->assertNotEmpty($this->config->getMessageRecordEqual());
    }

    public function testBooleanConversionWorks() {
        // assert options that cannot be set from the xml are set
        $this->assertInternalType('bool', $this->config->getDisplayRecordMessagesForBestOnly());
        $this->assertEquals(true, $this->config->getDisplayRecordMessagesForBestOnly());
    }
}
