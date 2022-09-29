<?php
declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\TestCase;

use function Platformsh\FlexBridge\mapPlatformShEnvironment;

class FlexBridgeElasticsearchTest extends TestCase
{
    protected $relationships;
    protected $defaultValues;

    public function setUp(): void
    {
        parent::setUp();

        $this->relationships = [
            'elasticsearch' => [
                [
                    'scheme'   => 'elasticsearch',
                    'username' => 'main_username',
                    'password' => 'main_password',
                    'host'     => 'elasticsearch.internal',
                    'port'     => 80,
                    'path'     => '',
                    'query'    => ['is_master' => true],
                ]
            ]
        ];

        $this->defaultValues = [];

    }

    public function testNotOnPlatformshDoesNotSetDatabase(): void
    {
        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('ELASTICSEARCH_HOST', $_SERVER);
        $this->assertArrayNotHasKey('ELASTICSEARCH_PORT', $_SERVER);
        $this->assertArrayNotHasKey('ELASTICSEARCH_URL', $_SERVER);
    }

    public function testNoElasticsearchRelationship(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        unset($rels['elasticsearch']);

        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('ELASTICSEARCH_HOST', $_SERVER);
        $this->assertArrayNotHasKey('ELASTICSEARCH_PORT', $_SERVER);
        $this->assertArrayNotHasKey('ELASTICSEARCH_URL', $_SERVER);
    }

    public function testElasticsearchRelationshipSet(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($this->relationships))));

        mapPlatformShEnvironment();

        $this->assertEquals('elasticsearch.internal', $_SERVER['ELASTICSEARCH_HOST']);
        $this->assertEquals(80, $_SERVER['ELASTICSEARCH_PORT']);
        $this->assertEquals('http://elasticsearch.internal:80', $_SERVER['ELASTICSEARCH_URL']);
    }
}
