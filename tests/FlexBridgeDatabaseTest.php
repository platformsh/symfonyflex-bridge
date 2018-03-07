<?php
declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\TestCase;

class FlexBridgeDatabaseTest extends TestCase
{

    protected $relationships;

    protected $defaultDbUrl;

    public function setUp()
    {
        parent::setUp();

        $this->relationships = [
            'database' => [
                [
                    'scheme' => 'mysql',
                    'username' => 'user',
                    'password' => '',
                    'host' => 'database.internal',
                    'port' => '3306',
                    'path' => 'main',
                    'query' => ['is_master' => true],
                ]
            ]
        ];

        $this->defaultDbUrl = sprintf(
            '%s://%s:%s@%s:%s/%s?charset=utf8mb4&serverVersion=10.2',
            'mysql',
            '',
            '',
            'localhost',
            3306,
            ''
        );
    }

    public function testNotOnPlatformshDoesNotSetDatabase() : void
    {
        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('DATABASE_URL', $_SERVER);
    }

    public function testNoRelationships() : void
    {
        // We assume no relationships array, but a PLATFORM_APPLICATION env var,
        // means we're in a build hook.

        putenv('PLATFORM_APPLICATION=test');

        //putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($this->relationships))));

        mapPlatformShEnvironment();

        $this->assertEquals($this->defaultDbUrl, $_SERVER['DATABASE_URL']);
    }

    public function testNoDatabaseRelationship() : void
    {
        putenv('PLATFORM_APPLICATION=test');

        $rels = $this->relationships;
        unset($rels['database']);

        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertEquals($this->defaultDbUrl, $_SERVER['DATABASE_URL']);
    }

    public function testDatabaseRelationshipSet() : void
    {
        putenv('PLATFORM_APPLICATION=test');
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($this->relationships))));

        mapPlatformShEnvironment();

        $this->assertEquals('mysql://user:@database.internal:3306/main?charset=utf8mb4&serverVersion=10.2', $_SERVER['DATABASE_URL']);
    }

}
