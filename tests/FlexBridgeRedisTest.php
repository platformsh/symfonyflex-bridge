<?php

declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\TestCase;

use function Platformsh\FlexBridge\mapPlatformShEnvironment;

class FlexBridgeRedisTest extends TestCase
{
    protected $relationships;
    protected $defaultValues;

    public function setUp(): void
    {
        parent::setUp();

        $this->relationships = [];
        $this->defaultValues = [];
    }

    public function testNotOnPlatformshDoesNotSetEnvVar(): void
    {
        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('CACHE_DSN', $_SERVER);
        $this->assertArrayNotHasKey('SESSION_REDIS_HOST', $_SERVER);
        $this->assertArrayNotHasKey('SESSION_REDIS_PORT', $_SERVER);
    }

    public function testNoRedisRelationship(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;

        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('CACHE_DSN', $_SERVER);
        $this->assertArrayNotHasKey('SESSION_REDIS_HOST', $_SERVER);
        $this->assertArrayNotHasKey('SESSION_REDIS_PORT', $_SERVER);
    }

    public function testRelationshipSet(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        $rels['redis'] = [$this->getMockRedisRelation('redis')];
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertEquals('redis.internal:6379', $_SERVER['CACHE_DSN']);
        $this->assertEquals('redis.internal', $_SERVER['SESSION_REDIS_HOST']);
        $this->assertEquals('6379', $_SERVER['SESSION_REDIS_PORT']);
    }

    public function testRelationshipOverrideSession(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        $rels['redis_session'] = [$this->getMockRedisRelation('redis_session', 123456)];
        $rels['redis'] = [$this->getMockRedisRelation('redis')];
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertEquals('redis.internal:6379', $_SERVER['CACHE_DSN']);
        $this->assertEquals('redis_session.internal', $_SERVER['SESSION_REDIS_HOST']);
        $this->assertEquals('123456', $_SERVER['SESSION_REDIS_PORT']);
    }

    public function testRelationshipSessionOnly(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        $rels['redis_session'] = [$this->getMockRedisRelation('redis_session', 123456)];
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('CACHE_DSN', $_SERVER);
        $this->assertEquals('redis_session.internal', $_SERVER['SESSION_REDIS_HOST']);
        $this->assertEquals('123456', $_SERVER['SESSION_REDIS_PORT']);
    }

    public function testRelationshipOverrideCache(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        $rels['redis_cache'] = [$this->getMockRedisRelation('redis_cache', 1234567)];
        $rels['redis'] = [$this->getMockRedisRelation('redis')];
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertEquals('redis_cache.internal:1234567', $_SERVER['CACHE_DSN']);
        $this->assertEquals('redis.internal', $_SERVER['SESSION_REDIS_HOST']);
        $this->assertEquals('6379', $_SERVER['SESSION_REDIS_PORT']);
    }

    public function testRelationshipOverrideCacheOnly(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        $rels['redis_cache'] = [$this->getMockRedisRelation('redis_cache', 1234567)];
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertEquals('redis_cache.internal:1234567', $_SERVER['CACHE_DSN']);
        $this->assertArrayNotHasKey('SESSION_REDIS_HOST', $_SERVER);
        $this->assertArrayNotHasKey('SESSION_REDIS_PORT', $_SERVER);
    }

    public function testRelationshipBoth(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        $rels['redis_cache'] = [$this->getMockRedisRelation('redis_cache', 1234567)];
        $rels['redis_session'] = [$this->getMockRedisRelation('redis_session', 123456)];
        $rels['redis'] = [$this->getMockRedisRelation('redis')];
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertEquals('redis_cache.internal:1234567', $_SERVER['CACHE_DSN']);
        $this->assertEquals('redis_session.internal', $_SERVER['SESSION_REDIS_HOST']);
        $this->assertEquals('123456', $_SERVER['SESSION_REDIS_PORT']);
    }

    public function testRelationshipBothNoFallback(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        $rels['redis_cache'] = [$this->getMockRedisRelation('redis_cache', 1234567)];
        $rels['redis_session'] = [$this->getMockRedisRelation('redis_session', 123456)];
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertEquals('redis_cache.internal:1234567', $_SERVER['CACHE_DSN']);
        $this->assertEquals('redis_session.internal', $_SERVER['SESSION_REDIS_HOST']);
        $this->assertEquals('123456', $_SERVER['SESSION_REDIS_PORT']);
    }

    protected function getMockRedisRelation(string $relationshipName, int $port = 6379): array
    {
        return [
            "service" => $relationshipName,
            "ip" => "203.0.113.0",
            "cluster" => "someCluster",
            "host" => "$relationshipName.internal",
            "rel" => "$relationshipName",
            "scheme" => "redis",
            "port" => $port
        ];
    }
}
