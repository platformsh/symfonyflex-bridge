<?php

declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\TestCase;

use function Platformsh\FlexBridge\mapPlatformShEnvironment;

class FlexBridgeRedisCacheTest extends TestCase
{
    protected $relationships;
    protected $defaultValues;

    public function setUp(): void
    {
        parent::setUp();

        $this->relationships = [
            "rediscache" => [
                [
                    
                    "service" => 'rediscache',
                    "ip" => "203.0.113.0",
                    "cluster" => "someCluster",
                    "host" => "rediscache.internal",
                    "rel" => "rediscache",
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

        $this->assertArrayNotHasKey('CACHE_DSN', $_SERVER);
    }

    public function testNoRedisRelationship(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        unset($rels['rediscache']);
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('CACHE_DSN', $_SERVER);
        $this->assertArrayNotHasKey('SESSION_REDIS_HOST', $_SERVER);
        $this->assertArrayNotHasKey('SESSION_REDIS_PORT', $_SERVER);
    }

    public function testRelationshipCache(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertEquals('rediscache.internal:6379', $_SERVER['CACHE_DSN']);
    }
}
