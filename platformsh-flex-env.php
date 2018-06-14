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

    // getenv() returns false, not null, because getenv() is dumb. That's
    // why we have all these extra ternaries.

    // Set the application secret if it's not already set.
    $secret = (getenv('APP_SECRET') ?: null)
        ?? $_SERVER['APP_SECRET']
        ?? (getenv('PLATFORM_PROJECT_ENTROPY') ?: null)
    ;
    setEnvVar('APP_SECRET', $secret);

    // Default to production. You can override this value by setting
    // `env:APP_ENV` as a project variable, or by adding it to the
    // .platform.app.yaml variables block.
    setEnvVar('APP_ENV', $_SERVER['APP_ENV'] ?? (getenv('APP_ENV') ?: null) ?? 'prod');

    if (!isset($_SERVER['DATABASE_URL'])) {
        mapPlatformShDatabase();
    }
}

/**
 * Sets an environment variable in all the myriad places PHP can store it.
 *
 * @param string $name
 *   The name of the variable to set.
 * @param null|string $value
 *   The value to set.  Null to unset it.
 */
function setEnvVar(string $name, ?string $value) : void
{
    if (!putenv("$name=$value")) {
        throw new \RuntimeException('Failed to create environment variable: ' . $name);
    }
    $order = ini_get('variables_order');
    if (stripos($order, 'e') !== false) {
        $_ENV[$name] = $value;
    }
    if (stripos($order, 's') !== false) {
        if (strpos($name, 'HTTP_') !== false) {
            throw new \RuntimeException('Refusing to add ambiguous environment variable ' . $name . ' to $_SERVER');
        }
        $_SERVER[$name] = $value;
    }
}

function mapPlatformShDatabase() : void
{
    $dbRelationshipName = 'database';

    // Set the DATABASE_URL for Doctrine, if necessary.
    # "mysql://root@127.0.0.1:3306/symfony?charset=utf8mb4&serverVersion=5.7";
    if (getenv('PLATFORM_RELATIONSHIPS')) {
        $relationships = json_decode(base64_decode(getenv('PLATFORM_RELATIONSHIPS'), true), true);
        if (isset($relationships[$dbRelationshipName])) {
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
                        $dbUrl .= '?charset=utf8mb4&serverVersion=mariadb-10.2.12';
                        break;

                    case 'pgsql':
                        // Postgres 9.6 is the latest supported version on Platform.sh
                        $dbUrl .= '?serverVersion=9.6';
                }

                setEnvVar('DATABASE_URL', $dbUrl);
                return;
            }
        }
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

    setEnvVar('DATABASE_URL', $dbUrl);
}
