<?php
declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\TestCase;

use function Platformsh\FlexBridge\mapPlatformShEnvironment;

class FlexBridgeSolrTest extends TestCase
{
    protected $relationships;
    protected $defaultValues;

    public function setUp(): void
    {
        parent::setUp();

        $this->relationships = [
            'solr' => [
                [
                    'service' => 'solrsearch',
                    'ip' => '169.254.69.109',
                    'hostname' => 'xxx.solrsearch.service._.us-2.platformsh.site',
                    'cluster' => 'xxx-master-7rqtwti',
                    'host' => 'solr.internal',
                    'rel' => 'collection1',
                    'path' => 'solr/collection1',
                    'scheme' => 'solr',
                    'type' => 'solr:7.7',
                    'port' => 8080
                ]
            ]
        ];

        $this->defaultValues = [];

    }

    public function testNotOnPlatformshDoesNotSetEnvVar(): void
    {
        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('SOLR_DSN', $_SERVER);
        $this->assertArrayNotHasKey('SOLR_CORE', $_SERVER);
    }

    public function testNoSolrRelationship(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        unset($rels['solr']);

        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('SOLR_DSN', $_SERVER);
        $this->assertArrayNotHasKey('SOLR_CORE', $_SERVER);
    }

    public function testRelationshipSet(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($this->relationships))));

        mapPlatformShEnvironment();

        $this->assertEquals('http://solr.internal:8080/solr', $_SERVER['SOLR_DSN']);
        $this->assertEquals('collection1', $_SERVER['SOLR_CORE']);
    }
}
