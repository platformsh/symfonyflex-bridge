<?php

declare(strict_types=1);

namespace Platformsh\FlexBridge;

use Platformsh\ConfigReader\Config;
const DEFAULT_MYSQL_ENDPOINT_TYPE = 'mysql:10.2';

const DEFAULT_POSTGRESQL_ENDPOINT_TYPE = 'postgresql:9.6';

mapPlatformShEnvironment();

/**
 * Map Platform.Sh environment variables to the values Symfony Flex expects.
 *
 * This is wrapped up into a function to avoid executing code in the global
 * namespace.
 */
function mapPlatformShEnvironment() : void
{
    $config = new Config();

    if (!$config->inRuntime()) {
        if ($config->inBuild()) {
            // In the build hook we still need to set a fake Doctrine URL in order to
            // work around bugs in Doctrine.
            setDefaultDoctrineUrl();
        }
        return;
    }

    $config->registerFormatter('doctrine', __NAMESPACE__ . '\doctrineFormatter');

    // Set the application secret if it's not already set.
    // We force re-setting the APP_SECRET to ensure it's set in all of PHP's various
    // environment places.
    $secret = getenv('APP_SECRET') ?: $config->projectEntropy;
    setEnvVar('APP_SECRET', $secret);

    // Default to production. You can override this value by setting
    // `env:APP_ENV` as a project variable, or by adding it to the
    // .platform.app.yaml variables block.
    $appEnv = getenv('APP_ENV') ?: 'prod';
    setEnvVar('APP_ENV', $appEnv);

    // Map services as feasible.
    mapPlatformShDatabase('database', $config);
    mapPlatformShMongoDatabase('mongodatabase', $config);
    mapPlatformShRabbitMq('rabbitmq', $config);

    // Set the Swiftmailer configuration if it's not set already.
    if (!getenv('MAILER_URL')) {
        mapPlatformShSwiftmailer($config);
    }

    // Set the Symfony Mailer configuration if it's not set already.
    if (!getenv('MAILER_DSN')) {
        mapPlatformShMailer($config);
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

function mapPlatformShSwiftmailer(Config $config)
{
    $mailUrl = sprintf(
        '%s://%s:%d/',
        !empty($config->smtpHost) ? 'smtp' : 'null',
        !empty($config->smtpHost) ? $config->smtpHost : 'localhost',
        25
    );

    setEnvVar('MAILER_URL', $mailUrl);
}

function mapPlatformShMailer(Config $config)
{
    $mailUrl = sprintf(
        '%s://%s:%d/',
        !empty($config->smtpHost) ? 'smtp' : 'null',
        !empty($config->smtpHost) ? $config->smtpHost : 'localhost',
        25
    );

    setEnvVar('MAILER_DSN', $mailUrl);
}

function doctrineFormatter(array $credentials) : string
{
    $dbUrl = sprintf(
        '%s://%s:%s@%s:%d/%s',
        $credentials['scheme'],
        $credentials['username'],
        $credentials['password'],
        $credentials['host'],
        $credentials['port'],
        $credentials['path']
    );

    switch ($credentials['scheme']) {
        case 'mysql':
            $type = $credentials['type'] ?? DEFAULT_MYSQL_ENDPOINT_TYPE;
            $versionPosition = strpos($type, ":");

            // If a version is found, use it, otherwise, default to mariadb 10.2.
            $dbVersion = (false !== $versionPosition) ? substr($type, $versionPosition + 1) : '10.2';

            // Doctrine needs the mariadb-prefix if it's an instance of MariaDB server
            if ($dbVersion !== '5.5') {
                $dbVersion = sprintf('mariadb-%s', $dbVersion);
            }

            // if MariaDB is in version 10.2, doctrine needs to know it's superior to patch version 6 to work properly
            if ($dbVersion === 'mariadb-10.2') {
                $dbVersion .= '.12';
            }

            $dbUrl .= sprintf('?charset=utf8mb4&serverVersion=%s', $dbVersion);
            break;
        case 'pgsql':
            $type = $credentials['type'] ?? DEFAULT_POSTGRESQL_ENDPOINT_TYPE;
            $versionPosition = strpos($type, ":");

            $dbVersion = (false !== $versionPosition) ? substr($type, $versionPosition + 1) : '11';
            $dbUrl .= sprintf('?serverVersion=%s', $dbVersion);
            break;
    }

    return $dbUrl;

}

/**
 * Maps the specified relationship to the DATABASE_URL environment variable, if available.
 *
 * @param string $relationshipName
 *   The database relationship name.
 * @param Config $config
 *   The config object.
 */
function mapPlatformShDatabase(string $relationshipName, Config $config) : void
{
    if (!$config->hasRelationship($relationshipName)) {
        return;
    }

    setEnvVar('DATABASE_URL', $config->formattedCredentials($relationshipName, 'doctrine'));
}

/**
 * Sets a default Doctrine URL.
 *
 * Doctrine needs a well-formed URL string with a database version even in the build hook.
 * It doesn't use it, but it fails if it's not there.  This default meets the minimum
 * requirements of the format without actually allowing a connection.
 */
function setDefaultDoctrineUrl() : void
{
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
        'mariadb-10.2.12'
    );

    setEnvVar('DATABASE_URL', $dbUrl);
}

/**
 * Maps the specified relationship to a Doctrine MongoDB connection, if available.
 *
 * MongoDB ODM uses a set of discrete environment variables rather than a single DB URL string
 * as in Doctrine ORM.  The related doctrine-odm-bundle settings should be:
 *
 * doctrine_mongodb:
 *     connections:
 *         default:
 *             server: '%env(MONGODB_SERVER)%'
 *             options: { username: '%env(MONGODB_USERNAME)%', password: '%env(MONGODB_PASSWORD)%', authSource: '%env(MONGODB_DB)%' }
 *     default_database: '%env(MONGODB_DB)%'
 *
 * @see https://symfony.com/doc/master/bundles/DoctrineMongoDBBundle/index.html
 *
 * @param string $relationshipName
 *   The MongoDB database relationship name.
 * @param Config $config
 *   The config object.
 */
function mapPlatformShMongoDatabase(string $relationshipName, Config $config): void
{
    if (!$config->hasRelationship($relationshipName)) {
        return;
    }

    $credentials = $config->credentials($relationshipName);

    setEnvVar('MONGODB_SERVER', sprintf('mongodb://%s:%d', $credentials['host'], $credentials['port']));
    setEnvVar('MONGODB_DB', $credentials['path']);
    setEnvVar('MONGODB_USERNAME', $credentials['username']);
    setEnvVar('MONGODB_PASSWORD', $credentials['password']);
}

function mapPlatformShRabbitMq(string $relationshipName, Config $config): void
{
    if (!$config->hasRelationship($relationshipName)) {
        return;
    }

    $credentials = $config->credentials($relationshipName);

    setEnvVar('MESSENGER_TRANSPORT_DSN', sprintf(
        '%s://%s:%s@%s:%d/%%2f/messages',
        $credentials['scheme'],
        $credentials['username'],
        $credentials['password'],
        $credentials['host'],
        $credentials['port']
    ));
}
