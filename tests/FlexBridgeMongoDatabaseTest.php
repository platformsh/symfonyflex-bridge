<?php
declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\TestCase;

use function Platformsh\FlexBridge\mapPlatformShEnvironment;

class FlexBridgeMongoDatabaseTest extends TestCase
{
    protected $relationships;
    protected $defaultValues;

    public function setUp(): void
    {
        parent::setUp();

        $this->relationships = [
            'mongodatabase' => [
                [
                    'scheme'   => 'mongodb',
                    'username' => 'main_username',
                    'password' => 'main_password',
                    'host'     => 'mongodatabase.internal',
                    'port'     => 27017,
                    'path'     => 'main',
                    'query'    => ['is_master' => true],
                ]
            ]
        ];

        $this->defaultValues = [];

    }

    public function testNotOnPlatformshDoesNotSetDatabase(): void
    {
        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('MONGODB_SERVER', $_SERVER);
        $this->assertArrayNotHasKey('MONGODB_DB', $_SERVER);
        $this->assertArrayNotHasKey('MONGODB_USERNAME', $_SERVER);
        $this->assertArrayNotHasKey('MONGODB_PASSWORD', $_SERVER);
    }

    public function testNoDatabaseRelationship(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        unset($rels['mongodatabase']);

        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('MONGODB_SERVER', $_SERVER);
        $this->assertArrayNotHasKey('MONGODB_DB', $_SERVER);
        $this->assertArrayNotHasKey('MONGODB_USERNAME', $_SERVER);
        $this->assertArrayNotHasKey('MONGODB_PASSWORD', $_SERVER);
    }

    public function testDatabaseRelationshipSet(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($this->relationships))));

        mapPlatformShEnvironment();

        $this->assertEquals('mongodb://mongodatabase.internal:27017', $_SERVER['MONGODB_SERVER']);
        $this->assertEquals('main', $_SERVER['MONGODB_DB']);
        $this->assertEquals('main_username', $_SERVER['MONGODB_USERNAME']);
        $this->assertEquals('main_password', $_SERVER['MONGODB_PASSWORD']);
    }
}
