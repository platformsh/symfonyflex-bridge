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

}
