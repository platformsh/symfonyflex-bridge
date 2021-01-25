<?php

declare(strict_types=1);

namespace Platformsh\FlexBridge;

use Platformsh\ConfigReader\Config;

// These are only for the Foundation 1.x regions. If you are using some other version
// please move to a newer region, where these values will not be needed.
const DEFAULT_MARIADB_VERSION = '10.2';
const DEFAULT_MYSQL_VERSION = '8.0';
const DEFAULT_POSTGRESQL_VERSION = '9.6';

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
    $config->registerFormatter('rabbitmq', __NAMESPACE__ . '\rabbitMqFormatter');

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
    mapPlatformShElasticSearch('elasticsearch', $config);
    mapPlatformShRabbitMq('rabbitmqqueue', $config);
    mapPlatformShSolr('solr', $config);

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

/**
 * Formatter for the Symfony RabbitMQ Messenger format.
 *
 * The %2f default vhost is not a formatting code, but a URL-encoded
 * forward slash (/), which is the default vhost name in RabbitMQ.
 * It's a weird default name, but it's what RabbitMQ uses.
 */
function rabbitMqFormatter(array $credentials): string
{
    return sprintf('%s://%s:%s@%s:%d/%s/messages',
        $credentials['scheme'],
        $credentials['username'],
        $credentials['password'],
        $credentials['host'],
        $credentials['port'],
        $credentials['vhost'] ?? '%2f'
    );
}

/**
 * Formatter for Doctrine's DB URL format.
 *
 * Note that non-default DB versions are not supported on Foundation 1 regions.
 * On those regions we cannot derive the version from the credential information
 * so the hard-coded defaults defined at the top of the file are used. To use a
 * different version of MariaDB or PostgreSQL, or to use Oracle MySQL at all,
 * move to a Foundation 3 region.
 */
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

    if (isset($credentials['type'])) {
        list($type, $version) = explode(':', $credentials['type']);
    }
    else {
        $type = $credentials['scheme'];
        $version = null;
    }

    // "mysql" is an alias for MariaDB, so use the same mapper for both.
    $mappers['mysql'] = __NAMESPACE__ . '\doctrineFormatterMariaDB';
    $mappers['mariadb'] = __NAMESPACE__ . '\doctrineFormatterMariaDB';
    $mappers['oracle-mysql'] = __NAMESPACE__ . '\doctrineFormatterOracleMySQL';
    // The "Scheme" is pgsql, while the type is postgresql. Both end up in the same place.
    $mappers['pgsql'] = __NAMESPACE__ . '\doctrineFormatterPostgreSQL';
    $mappers['postgresql'] = __NAMESPACE__ . '\doctrineFormatterPostgreSQL';

    // Add a query suffix that is appropriate to the specific DB type to handle version information.
    return $dbUrl . $mappers[$type]($version);
}

function doctrineFormatterMariaDB(?string $version) : string
{
    $version = $version ?? DEFAULT_MARIADB_VERSION;

    $version = sprintf('mariadb-%s', $version);

    // Doctrine requires a full version number, even though it doesn't really matter what the patch version is,
    // except for MariaDB 10.2, where it needs a verison greater than 10.2.6 to avoid certain bugs.
    $version .= ($version === 'mariadb-10.2') ? '.12' : '.0';

    return sprintf('?charset=utf8mb4&serverVersion=%s', $version);
}

function doctrineFormatterOracleMySQL(?string $version) : string
{
    $version = $version ?? DEFAULT_MYSQL_VERSION;

    return sprintf('?charset=utf8mb4&serverVersion=%s', $version);
}

function doctrineFormatterPostgreSQL(?string $version) : string
{
    $version = $version ?? DEFAULT_POSTGRESQL_VERSION;

    $suffix = sprintf('?serverVersion=%s', $version);

    return $suffix;
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
 * Maps the specified relationship to the MESSENGER_TRANSPORT_DSN environment variable, if available.
 *
 * @param string $relationshipName
 *   The database relationship name.
 * @param Config $config
 *   The config object.
 */
function mapPlatformShRabbitMq(string $relationshipName, Config $config) : void
{
    if (!$config->hasRelationship($relationshipName)) {
        return;
    }

    setEnvVar('MESSENGER_TRANSPORT_DSN', $config->formattedCredentials($relationshipName, 'rabbitmq'));
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

/**
 * Maps the specified relationship to environment variables for Elasticsearch.
 *
 * @param string $relationshipName
 * @param Config $config
 */
function mapPlatformShElasticSearch(string $relationshipName, Config $config): void
{
    if (!$config->hasRelationship($relationshipName)) {
        return;
    }

    $credentials = $config->credentials($relationshipName);

    setEnvVar('ELASTICSEARCH_HOST', $credentials['host']);
    setEnvVar('ELASTICSEARCH_PORT', (string)$credentials['port']);
}

/**
 * Maps the specified relationship to environment variables for Solr.
 *
 * @param string $relationshipName
 * @param Config $config
 */
function mapPlatformShSolr(string $relationshipName, Config $config): void
{
    if (!$config->hasRelationship($relationshipName)) {
        return;
    }

    $credentials = $config->credentials($relationshipName);

    setEnvVar('SOLR_DSN', sprintf('http://%s:%d/solr', $credentials['host'], $credentials['port']));
    setEnvVar('SOLR_CORE', $credentials['rel']);
}
