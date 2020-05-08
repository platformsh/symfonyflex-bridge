<?php

namespace Platformsh\FlexBridge;

// ensure backward compatibility
function setEnvVar(string $name, ?string $value): void
{
    PlatformshFlexEnv::setEnvVar($name, $value);
}

// ensure backward compatibility
function mapPlatformShEnvironment(): void
{
    (new PlatformshFlexEnv())->mapPlatformShEnvironment();
}
