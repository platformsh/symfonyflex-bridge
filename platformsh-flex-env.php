<?php

declare(strict_types=1);

mapPlatformShEnvironment();

/**
 * Map Platform.Sh environment variables to the values Symfony Flex expects.
 *
 * This is wrapped up into a function to avoid executing code in the global
 * namespace.
 */
function mapPlatformShEnvironment() : void
{

    $dbRelationshipName = 'database';
    // Set the DATABASE_URL for Doctrine, if necessary.
    if (!getenv('DATABASE_URL')) {
        # "mysql://root@127.0.0.1:3306/symfony?charset=utf8mb4&serverVersion=5.7";
        if (isset($_ENV['PLATFORM_RELATIONSHIPS'])) {
            $relationships = json_decode(base64_decode(getenv('PLATFORM_RELATIONSHIPS')),true);
            foreach ($relationships[$dbRelationshipName] as $endpoint) {
                $dbUrl = '';
                if (!empty($endpoint['query']['is_master'])) {
                    $dbUrl = sprintf("%s://%s:%s@%s:%s/%s?charset=utf8mb4&serverVersion=10.2",
                        $endpoint['scheme'], $endpoint['username'], $endpoint['password'],
                        $endpoint['host'], $endpoint['port'],
                        $endpoint['path']);
                    putenv('DATABASE_URL=' . $dbUrl);
                    break;
                }
            }
        }
        else {
            // Hack the Doctrine URL to be syntactically valid in a build hook, even
            // though it shouldn't be used.
            $dbUrl = sprintf("%s://%s:%s@%s:%s/%s?charset=utf8mb4&serverVersion=10.2",
                'mysql', '', '',
                'localhost', 3306,
                '');
            $_ENV['DATABASE_URL'] = $dbUrl;
        }
    }

    // Set the application secret if it's not already set.
    if (!getenv('APP_SECRET') && getenv('PLATFORM_PROJECT_ENTROPY')) {
        putenv('APP_SECRET=' . getenv('PLATFORM_PROJECT_ENTROPY'));
    }

    // Default to production. You can override this value by setting
    // `env:APP_ENV` as a project variable, or by adding it to the
    // .platform.app.yaml variables block.
    if (!getenv('APP_ENV')) {
        putenv('APP_ENV=prod');
    }
}
