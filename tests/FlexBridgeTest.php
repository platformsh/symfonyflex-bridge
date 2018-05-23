<?php
declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\TestCase;

class FlexBridgeTest extends TestCase
{

    public function testDoesNotRunWithoutPlatformshVariables() : void
    {
        mapPlatformShEnvironment();

        $this->assertFalse(getenv('APP_SECRET'));
    }

    public function testSetAppSecret() : void
    {
        putenv('PLATFORM_APPLICATION=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');

        mapPlatformShEnvironment();

        $this->assertEquals('test', $_SERVER['APP_SECRET']);
        $this->assertEquals('test', getenv('APP_SECRET'));
    }

    public function testDontChangeAppSecret() : void
    {
        putenv('PLATFORM_APPLICATION=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        $_SERVER['APP_SECRET'] = 'original';

        mapPlatformShEnvironment();

        $this->assertEquals('original', $_SERVER['APP_SECRET']);
        $this->assertEquals('original', getenv('APP_SECRET'));
    }

    public function testAppEnvAlreadySetInServer() : void
    {
        putenv('PLATFORM_APPLICATION=test');
        $_SERVER['APP_ENV'] = 'dev';

        mapPlatformShEnvironment();

        $this->assertEquals('dev', $_SERVER['APP_ENV']);
        $this->assertEquals('dev', getenv('APP_ENV'));
    }

    public function testAppEnvAlreadySetInEnv() : void
    {
        putenv('PLATFORM_APPLICATION=test');
        putenv('APP_ENV=dev');

        mapPlatformShEnvironment();

        $this->assertEquals('dev', $_SERVER['APP_ENV']);
        $this->assertEquals('dev', getenv('APP_ENV'));
    }

    public function testAppEnvNeedsDefault() : void
    {
        putenv('PLATFORM_APPLICATION=test');

        mapPlatformShEnvironment();

        $this->assertEquals('prod', $_SERVER['APP_ENV']);
        $this->assertEquals('prod', getenv('APP_ENV'));
    }

}
