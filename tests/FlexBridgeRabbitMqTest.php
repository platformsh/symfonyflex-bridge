<?php
declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use Platformsh\ConfigReader\Config;
use function Platformsh\FlexBridge\mapPlatformShRabbitMq;

class FlexBridgeRabbitMqTest extends TestCase
{
    /**
     * @var MockObject|Config
     */
    private $configMock;

    public function setUp(): void
    {
        $this->configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testNoRelationshipReturnsNothing(): void
    {
        $relationshipName = 'rel_name';
        $this->configMock->expects($this->once())
            ->method('hasRelationship')
            ->with($relationshipName)
            ->willReturn(false);
        $this->configMock->expects($this->never())
            ->method('credentials');

        mapPlatformShRabbitMq('rel_name', $this->configMock);
    }

    public function testWithRelationshipExposesEnvVar(): void
    {
        $relationshipName = 'rel_name';
        $credentials = [
            'scheme' => 'amqp',
            'username' => 'foo',
            'password' => 'bar',
            'host' => 'localhost',
            'port' => 5672,
        ];

        $this->configMock->expects($this->once())
            ->method('hasRelationship')
            ->with($relationshipName)
            ->willReturn(true);
        $this->configMock->expects($this->once())
            ->method('credentials')
            ->with($relationshipName)
            ->willReturn($credentials);

        mapPlatformShRabbitMq($relationshipName, $this->configMock);

        $this->assertArrayHasKey('MESSENGER_TRANSPORT_DSN', $_SERVER);
        $this->assertEquals('amqp://foo:bar@localhost:5672/%2f/messages', $_SERVER['MESSENGER_TRANSPORT_DSN']);
    }
}
