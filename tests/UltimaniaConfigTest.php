<?php


use PHPUnit\Framework\TestCase;

class UltimaniaConfigTest extends TestCase {

    public function testInstantiateFromFileWorks() {
        getcwd(); // if i remove this, the working directory is not set properly. wtf?
        $config = UltimaniaConfig::instantiateFromFile('../src/ultimania.xml');

        // assert options that cannot be set from the xml are set
        $this->assertNotEmpty($config->getRequestTimeout());

        // assert options that are set from the xml are set
        $this->assertNotEmpty($config->getMessageRecordEqual());
    }
}
