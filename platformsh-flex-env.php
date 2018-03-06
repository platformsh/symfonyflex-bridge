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
    // If this env var is not set then we're not on a Platform.sh
    // environment or in the build hook, so don't try to do anything.
    if (!getenv('PLATFORM_APPLICATION')) {
        return;
    }

    // Set the application secret if it's not already set.
    if (!isset($_SERVER['APP_SECRET']) && isset($_SERVER['PLATFORM_PROJECT_ENTROPY'])) {
        $_SERVER['APP_SECRET'] = $_SERVER['PLATFORM_PROJECT_ENTROPY'];
    }

    // Default to production. You can override this value by setting
    // `env:APP_ENV` as a project variable, or by adding it to the
    // .platform.app.yaml variables block.
    if (!isset($_SERVER['APP_ENV'])) {
        $_SERVER['APP_ENV'] = 'prod';
    }

    if (!isset($_SERVER['DATABASE_URL'])) {
        mapPlatformShDatabase();
    }
}

function mapPlatformShDatabase() : void
{

    $dbRelationshipName = 'database';

    // Set the DATABASE_URL for Doctrine, if necessary.
    # "mysql://root@127.0.0.1:3306/symfony?charset=utf8mb4&serverVersion=5.7";
    if (isset($_SERVER['PLATFORM_RELATIONSHIPS'])) {
        $relationships = json_decode(base64_decode($_SERVER['PLATFORM_RELATIONSHIPS']), true);
        foreach ($relationships[$dbRelationshipName] as $endpoint) {
            if (empty($endpoint['query']['is_master'])) {
                continue;
            }

            $dbUrl = sprintf(
                '%s://%s:%s@%s:%d/%s',
                $endpoint['scheme'],
                $endpoint['username'],
                $endpoint['password'],
                $endpoint['host'],
                $endpoint['port'],
                $endpoint['path']
            );

            switch ($endpoint['scheme']) {
                case 'mysql':
                    // Defaults to the latest MariaDB version
                    $dbUrl .= '?serverVersion=10.2&charset=utf8mb4';
                    break;

                case 'pgsql':
                    // Postgres 9.6 is the latest supported version on Platform.sh
                    $dbUrl .= '?serverVersion=9.6';
            }

            $_SERVER['DATABASE_URL'] = $dbUrl;

            return;
        }

        return;
    }

    // Hack the Doctrine URL to be syntactically valid in a build hook, even
    // though it shouldn't be used.
    $dbUrl = sprintf(
        '%s://%s:%s@%s:%s/%s?charset=utf8mb4&serverVersion=10.2',
        'mysql',
        '',
        '',
        'localhost',
        3306,
        ''
    );

    $_SERVER['DATABASE_URL'] = $dbUrl;

}
