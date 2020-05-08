<?php

declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\TestCase;
use Platformsh\FlexBridge\PlatformshFlexEnv;

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
                    'scheme' => 'mongodb',
                    'username' => 'main_username',
                    'password' => 'main_password',
                    'host' => 'mongodatabase.internal',
                    'port' => 27017,
                    'path' => 'main',
                    'query' => ['is_master' => true],
                ]
            ],
            'other_mongodatabase' => [
                [
                    'scheme' => 'mongodb',
                    'username' => 'main_username2',
                    'password' => 'main_password2',
                    'host' => 'mongodatabase.internal',
                    'port' => 27017,
                    'path' => 'other_main',
                    'query' => ['is_master' => true],
                ]
            ]
        ];

        $this->defaultValues = [];
    }

    public function testNotOnPlatformshDoesNotSetDatabase(): void
    {
        (new PlatformshFlexEnv())->mapPlatformShEnvironment();

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

        putenv($this->encodeRelationships($rels));

        (new PlatformshFlexEnv())->mapPlatformShEnvironment();

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
        putenv($this->encodeRelationships($this->relationships));

        (new PlatformshFlexEnv())->mapPlatformShEnvironment();

        $this->assertEquals('mongodb://mongodatabase.internal:27017', $_SERVER['MONGODB_SERVER']);
        $this->assertEquals('main', $_SERVER['MONGODB_DB']);
        $this->assertEquals('main_username', $_SERVER['MONGODB_USERNAME']);
        $this->assertEquals('main_password', $_SERVER['MONGODB_PASSWORD']);
    }

    /**
     * @dataProvider databaseNameProvider
     * @param string $dbname
     * @param string|null $expected
     */
    public function testDatabaseRelationshipSetFromEnv(string $dbname, array $expected): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');
        putenv($this->encodeRelationships($this->relationships));

        putenv('APP_MONGODB_USERNAME=' . $dbname);

        (new PlatformshFlexEnv())->mapPlatformShEnvironment();

        $this->assertEquals($expected['server'], $_SERVER['MONGODB_SERVER']);
        $this->assertEquals($expected['db'], $_SERVER['MONGODB_DB']);
        $this->assertEquals($expected['username'], $_SERVER['MONGODB_USERNAME']);
        $this->assertEquals($expected['password'], $_SERVER['MONGODB_PASSWORD']);
    }

    public function databaseNameProvider(): iterable
    {
        yield 'default' => [
            'dbname' => 'mongodatabase',
            'expected' => [
                'server' => 'mongodb://mongodatabase.internal:27017',
                'db' => 'main',
                'username' => 'main_username',
                'password' => 'main_password',
            ]
        ];

        yield 'database2' => [
            'dbname' => 'other_mongodatabase',
            'expected' => [
                'server' => 'mongodb://mongodatabase.internal:27017',
                'db' => 'other_main',
                'username' => 'main_username2',
                'password' => 'main_password2',
            ]
        ];

        yield 'non_existing_database_key' => [
            'dbname' => 'fake_mongodatabase',
            'expected' => [
                'server' => null,
                'db' => null,
                'username' => null,
                'password' => null,
            ]
        ];
    }

    protected function encodeRelationships($rels): string
    {
        return sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels)));
    }
}
