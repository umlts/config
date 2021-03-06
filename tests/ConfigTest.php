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
        $config = new Config( __DIR__ );
        $this->assertInstanceOf( Config::class, $config );
    }

    public function testSetAndGet() {
        $config = new Config( __DIR__ );
        $config->set( 'root_var', 'root_value' );
        $config->set( 'test_ns/key1', 'value1' );

        $this->assertEquals( $config->get( 'test_ns/key1' ), 'value1' );

        $config->setNamespace( 'test_ns' );
        $this->assertEquals( $config->get( 'key1' ), 'value1' );

        $this->assertEquals( $config->get( '/root_var' ), 'root_value' );

        $this->assertTrue( $config->exists( '/root_var' ) );
        $this->assertTrue( $config->exists( 'key1' ) );
        $this->assertTrue( $config->exists( '/test_ns/key1' ) );
        $this->assertFalse( $config->exists( '/test_ns/key2' ) );
        $this->assertFalse( $config->exists( 't' ) );
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

    public function testNamespace() {
        $config = new Config( __DIR__ );

        $this->assertEquals(
            $config->get( 'group1/prop1' ),
            $config->setNamespace( 'group1' )->get( 'prop1' )
        );

        $this->assertEquals(
            $config->setNamespace( '/' )->get( 'group1' ),
            $config->get( 'group1' )
        );

        $this->assertEquals(
            $config->setNamespace( '/' )->get( 'group1' ),
            $config->setNamespace( 'group1' )->get( '/group1' )
        );

        $config->setNamespace( '/' );
        $this->assertEquals(
            $config->get( 'group1/prop1' ),
            $config
                ->setNamespace( 'group1' )
                ->setNamespace( '/group1' )
                ->get( 'prop1' )
        );
    }

    public function testNamespaceExceptions() {
        $config = new Config( __DIR__ );

        $this->expectException( \InvalidArgumentException::class );
        $config->setNamespace( 'invalid/namespace' );    
    }

    public function testClone() {
        $config = new Config( __DIR__ );
        $clone = $config->clone( 'group1' );
        $this->assertEquals( $config->get( 'group1/prop1' ), $clone->get('prop1') );
    }

    public function testComment() {
        $config = new Config( __DIR__ );
        $json = '
            {
                # Nothing!
                "prop3": "base #comment value for property 3"
                # Just a comment
            }';

        $json_wo_comment = '
            {
                "prop3": "base #comment value for property 3"
            }';

        $this->assertEquals( $json_wo_comment, $config->removeComment( $json ) );
    }

}
