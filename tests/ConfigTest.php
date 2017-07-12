<?php

declare( strict_types = 1 );

namespace Umlts\Config;

use PHPUnit\Framework\TestCase;
use Umlts\Config\Config;

/**
 * @covers Umlts\Config
 */
final class ConfigTest extends TestCase {
    
    public function testCanBeCreated() {
        $config = new Config();
        $this->assertInstanceOf( Config::class, $config );
    }
    
    public function testSetAndGet() {
        $config = new Config();
        $config->set( 'test_ns/key1', 'value1' );
        
        $this->assertEquals( $config->get( 'test_ns/key1' ), 'value1' );
        
        $config->setNamespace( 'test_ns' );
        $this->assertEquals( $config->get( 'key1' ), 'value1' );
    }
    
    public function testLoadsFilesCorrectly() {
        $config = new Config( __DIR__, TRUE );
        $config->load( __DIR__ . '/config/config.json' );
        
        $this->assertEquals( $config->get( 'group1/prop1', 'nope' ), 'base value for property 1' );
        
        $config->load( __DIR__ . '/config/config.yml' );
        $this->assertEquals( $config->get( 'group1/prop1', 'nope' ), 'yaml value for property 1' );
        $this->assertEquals( $config->get( 'group1/prop2', 'nope' ), 'base value for property 2' );
        
        $config->load( __DIR__ . '/config/config.ini' );
        $this->assertEquals( $config->get( 'group1/prop1', 'nope' ), 'yaml value for property 1' );
        $this->assertEquals( $config->get( 'group1/prop2', 'nope' ), 'ini value for property 1' );
        $this->assertEquals( $config->get( 'group1/prop5', 'nope' ), 'additional value from ini' );
    }
    
}