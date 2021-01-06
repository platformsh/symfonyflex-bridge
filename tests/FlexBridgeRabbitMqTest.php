<?php
declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\TestCase;

use function Platformsh\FlexBridge\mapPlatformShEnvironment;

class FlexBridgeRabbitMqTest extends TestCase
{
    protected $relationships;
    protected $defaultValues;

    public function setUp(): void
    {
        parent::setUp();

        $this->relationships = [
            'rabbitmqqueue' => [
                [
                    'scheme'   => 'amqp',
                    'username' => 'guest',
                    'password' => 'guest',
                    'host'     => 'rabbitmq.internal',
                    'port'     => 5672,
                    'path'     => null,
                    'query'    => [],
                ]
            ]
        ];

        $this->defaultValues = [];

    }

    public function testNotOnPlatformshDoesNotSetDatabase(): void
    {
        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('MESSENGER_TRANSPORT_DSN', $_SERVER);
    }

    public function testNoRabbitMqRelationship(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        $rels = $this->relationships;
        unset($rels['rabbitmqqueue']);

        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($rels))));

        mapPlatformShEnvironment();

        $this->assertArrayNotHasKey('MESSENGER_TRANSPORT_DSN', $_SERVER);
    }

    public function testElasticsearchRelationshipSet(): void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');
        putenv(sprintf('PLATFORM_RELATIONSHIPS=%s', base64_encode(json_encode($this->relationships))));

        mapPlatformShEnvironment();

        $this->assertEquals('amqp://guest:guest@rabbitmq.internal:5672/%2f/messages', $_SERVER['MESSENGER_TRANSPORT_DSN']);
    }
}
