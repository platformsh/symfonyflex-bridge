<?php
declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\TestCase;

use function Platformsh\FlexBridge\mapPlatformShEnvironment;

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
                    'type' => 'mysql:10.2'
                ]
            ]
        ];

        $this->defaultDbUrl = sprintf(
            '%s://%s:%s@%s:%s/%s?charset=utf8mb4&serverVersion=mariadb-10.2.12',
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

    public function testNoRelationshipsBecauseBuild() : void
    {
        // Application name but no environment name means build hook.

        putenv('PLATFORM_APPLICATION_NAME=test');

        //putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($this->relationships))));

        mapPlatformShEnvironment();

        $this->assertEquals($this->defaultDbUrl, $_SERVER['DATABASE_URL']);
    }

    public function testNoDatabaseRelationshipInRuntime() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');

        $rels = $this->relationships;
        unset($rels['database']);

        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('DATABASE_URL', $_SERVER);
    }

    public function testDatabaseRelationshipSet() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($this->relationships))));

        mapPlatformShEnvironment();
        $this->assertEquals('mysql://user:@database.internal:3306/main?charset=utf8mb4&serverVersion=mariadb-10.2.12', $_SERVER['DATABASE_URL']);
    }

    public function testDatabaseRelationshipOnFoundation1() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');

        $rels = $this->relationships;

        // On Foundation 1 regions there is no `type` key, so make sure nothing dies.
        unset($rels['database'][0]['type']);

        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertEquals('mysql://user:@database.internal:3306/main?charset=utf8mb4&serverVersion=mariadb-10.2.12', $_SERVER['DATABASE_URL']);
    }

}
