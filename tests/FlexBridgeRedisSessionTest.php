<?php

declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\TestCase;

use function Platformsh\FlexBridge\mapPlatformShEnvironment;

class FlexBridgeSessionTest extends TestCase
{
    protected $relationships;
    protected $defaultValues;

    public function setUp(): void
    {
        parent::setUp();

        $this->relationships = [
            "redissession" => [
                [

                    "service" => 'redissession',
                    "ip" => "203.0.113.0",
                    "cluster" => "someCluster",
                    "host" => "redissession.internal",
                    "rel" => "redissession",
                    "scheme" => "redis",
                    "port" => 6379

                ]
            ]
        ];
        $this->defaultValues = [];
    }

    public function testNotOnPlatformshDoesNotSetEnvVar(): void
    {
        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('SESSION_REDIS_HOST', $_SERVER);
        $this->assertArrayNotHasKey('SESSION_REDIS_PORT', $_SERVER);
        $this->assertArrayNotHasKey('SESSION_REDIS_URL', $_SERVER);
    }

    public function testNoRedisRelationship(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        unset($rels['redissession']);
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('SESSION_REDIS_HOST', $_SERVER);
        $this->assertArrayNotHasKey('SESSION_REDIS_PORT', $_SERVER);
        $this->assertArrayNotHasKey('SESSION_REDIS_URL', $_SERVER);
    }

    public function testRelationshipSession(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertEquals('redissession.internal', $_SERVER['SESSION_REDIS_HOST']);
        $this->assertEquals('6379', $_SERVER['SESSION_REDIS_PORT']);
        $this->assertEquals('redis://redissession.internal:6379', $_SERVER['SESSION_REDIS_URL']);
    }
}
