<?php
declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\TestCase;

class FlexBridgeTest extends TestCase
{

    public function testDoesNotRunWithoutPlatformshVariables()
    {
        mapPlatformShEnvironment();

        $this->assertFalse(getenv('APP_SECRET'));
    }

    public function testSetAppSecret()
    {
        putenv('PLATFORM_APPLICATION=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');

        mapPlatformShEnvironment();

        $this->assertEquals('test', $_SERVER['APP_SECRET']);
    }

    public function testDontChangeAppSecret()
    {
        putenv('PLATFORM_APPLICATION=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        $_SERVER['APP_SECRET'] = 'original';

        mapPlatformShEnvironment();

        $this->assertEquals('original', $_SERVER['APP_SECRET']);
    }

}
