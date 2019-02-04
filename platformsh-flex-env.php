<?php

declare(strict_types=1);

namespace Platformsh\FlexBridge;


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
    $secret = getenv('APP_SECRET') ?: getenv('PLATFORM_PROJECT_ENTROPY') ?: null;
    setEnvVar('APP_SECRET', $secret);

    // Default to production. You can override this value by setting
    // `env:APP_ENV` as a project variable, or by adding it to the
    // .platform.app.yaml variables block.
    $appEnv = getenv('APP_ENV') ?: 'prod';
    setEnvVar('APP_ENV', $appEnv);

    if (!getenv('DATABASE_URL')) {
        setEnvVar('DATABASE_URL', mapPlatformShDatabase());
    }
    if (!getenv('MAILER_URL')) {
        setEnvVar('MAILER_URL', mapPlatformShSwiftmailer());
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

function mapPlatformShSwiftmailer() : string
{
    $mailUrl = sprintf(
        '%s://%s:%d/',
        'smtp',
        getenv('PLATFORM_SMTP_HOST'),
        25
    );

    return $mailUrl;
}

function mapPlatformShDatabase() : string
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
                        $type = $endpoint['type'] ?? 'mysql:10.2';
                        $versionPosition = strpos($type, ":");

                         // If version is found, use it, otherwise, default to mariadb 10.2
                        $dbVersion = (false !== $versionPosition) ? substr($type, $versionPosition + 1) : '10.2';

                        // doctrine needs the mariadb-prefix if it's an instance of MariaDB server
                        if ($dbVersion !== '5.5') {
                            $dbVersion = sprintf('mariadb-%s', $dbVersion);
                        }

                        // if MariaDB is in version 10.2, doctrine needs to know it's superior to patch version 6 to work properly
                        if ($dbVersion === 'mariadb-10.2') {
                            $dbVersion = sprintf('%s.12', $dbVersion);
                        }   
                        
                        $dbUrl .= sprintf('?charset=utf8mb4&serverVersion=%s', $dbVersion);
                        break;
                    case 'pgsql':
                        $type = $endpoint['type'] ?? 'postgresql:9.6';
                        $versionPosition = strpos($type, ":");

                        $dbVersion = (false !== $versionPosition) ? substr($type, $versionPosition + 1) : '11';
                        $dbUrl .= sprintf('?serverVersion=%s', $dbVersion);
                        break;
                }

                return $dbUrl;
            }
        }
    }

    // Hack the Doctrine URL to be syntactically valid in a build hook, even
    // though it shouldn't be used.
    $dbUrl = sprintf(
        '%s://%s:%s@%s:%s/%s?charset=utf8mb4&serverVersion=%s',
        'mysql',
        '',
        '',
        'localhost',
        3306,
        '',
        $dbVersion ?? 'mariadb-10.2.12'
    );

    return $dbUrl;
}
