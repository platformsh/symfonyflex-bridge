<?php
declare(strict_types=1);

namespace Platformsh\FlexBridge\Tests;

use PHPUnit\Framework\TestCase;

use function Platformsh\FlexBridge\mapPlatformShEnvironment;

class FlexBridgeTest extends TestCase
{

    public function testDoesNotRunWithoutPlatformshVariables() : void
    {
        mapPlatformShEnvironment();

        $this->assertFalse(getenv('APP_SECRET'));
    }

    public function testSetAppSecret() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');

        mapPlatformShEnvironment();

        $this->assertEquals('test', $_SERVER['APP_SECRET']);
        $this->assertEquals('test', getenv('APP_SECRET'));
    }

    public function testDontChangeAppSecret() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');
        putenv('APP_SECRET=original');

        mapPlatformShEnvironment();

        $this->assertEquals('original', $_SERVER['APP_SECRET']);
        $this->assertEquals('original', getenv('APP_SECRET'));
    }

    public function testAppEnvAlreadySetInServer() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');
        putenv('APP_ENV=dev');

        mapPlatformShEnvironment();

        $this->assertEquals('dev', $_SERVER['APP_ENV']);
        $this->assertEquals('dev', getenv('APP_ENV'));
    }

    public function testAppEnvAlreadySetInEnv() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');
        putenv('APP_ENV=dev');

        mapPlatformShEnvironment();

        $this->assertEquals('dev', $_SERVER['APP_ENV']);
        $this->assertEquals('dev', getenv('APP_ENV'));
    }

    public function testAppEnvNeedsDefault() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');
        putenv('PLATFORM_PROJECT_ENTROPY=test');

        mapPlatformShEnvironment();

        $this->assertEquals('prod', $_SERVER['APP_ENV']);
        $this->assertEquals('prod', getenv('APP_ENV'));
    }

    public function testSwiftmailer() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');
        putenv('PLATFORM_PROJECT_ENTROPY=test');

        mapPlatformShEnvironment();

        $this->assertEquals('smtp://1.2.3.4:25/', $_SERVER['MAILER_URL']);
        $this->assertEquals('smtp://1.2.3.4:25/', getenv('MAILER_URL'));
    }

    public function testSwiftmailerDisabledMailEnvVarEmpty() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=');

        mapPlatformShEnvironment();

        $this->assertEquals('null://localhost:25/', $_SERVER['MAILER_URL']);
        $this->assertEquals('null://localhost:25/', getenv('MAILER_URL'));
    }

    public function testSwiftmailerDisabledMailEnvVarNotDefined() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');

        mapPlatformShEnvironment();

        $this->assertEquals('null://localhost:25/', $_SERVER['MAILER_URL']);
        $this->assertEquals('null://localhost:25/', getenv('MAILER_URL'));
    }

    public function testMailer() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_SMTP_HOST=1.2.3.4');
        putenv('PLATFORM_PROJECT_ENTROPY=test');

        mapPlatformShEnvironment();

        $this->assertEquals('smtp://1.2.3.4:25/', $_SERVER['MAILER_DSN']);
        $this->assertEquals('smtp://1.2.3.4:25/', getenv('MAILER_DSN'));
    }

    public function testMailerDisabledMailEnvVarEmpty() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');
        putenv('PLATFORM_SMTP_HOST=');

        mapPlatformShEnvironment();

        $this->assertEquals('null://localhost:25/', $_SERVER['MAILER_DSN']);
        $this->assertEquals('null://localhost:25/', getenv('MAILER_DSN'));
    }

    public function testMailerDisabledMailEnvVarNotDefined() : void
    {
        putenv('PLATFORM_APPLICATION_NAME=test');
        putenv('PLATFORM_ENVIRONMENT=test');
        putenv('PLATFORM_PROJECT_ENTROPY=test');

        mapPlatformShEnvironment();

        $this->assertEquals('null://localhost:25/', $_SERVER['MAILER_DSN']);
        $this->assertEquals('null://localhost:25/', getenv('MAILER_DSN'));
    }
}
