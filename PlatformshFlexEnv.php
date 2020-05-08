<?php

declare(strict_types=1);

namespace Platformsh\FlexBridge;

use Platformsh\ConfigReader\Config;

/**
 * Class PlatformshFlexEnv
 * Code taken from package Platformsh\FlexBridge and encapsulated in a class
 * @package App\Common
 */
class PlatformshFlexEnv
{

    // These are only for the Foundation 1.x regions. If you are using some other version
    // please move to a newer region, where these values will not be needed.
    public const DEFAULT_MARIADB_VERSION = '10.2';
    public const DEFAULT_MYSQL_VERSION = '8.0';
    public const DEFAULT_POSTGRESQL_VERSION = '9.6';

    /**
     * The default name of the database relationship
     * @var string
     */
    protected const DB_NAME_DEFAULT = 'database';

    /**
     * The default name of the mongo database relationship
     * @var string
     */
    protected const MONGODB_NAME_DEFAULT = 'mongodatabase';
    /**
     * The name of the database relationship
     * @var string
     */
    protected $dbName;

    /**
     * The name of the mongo database relationship
     * @var string
     */
    protected $mongodbName;

    public function __construct()
    {
        $this->initdbRelationshipNames();
    }

    /**
     * Initialize the dbName relationship key and mongodbName relationship key
     * based on ENV vars APP_DB_USERNAME and APP_MONGODB_USERNAME if they exists
     * otherwise it use the dbNameDefault and mongodbNameDefault values
     */
    protected function initdbRelationshipNames(): void
    {
        $this->dbName = getenv('APP_DB_USERNAME') ?: static::DB_NAME_DEFAULT;
        $this->mongodbName = getenv('APP_MONGODB_USERNAME') ?: static::MONGODB_NAME_DEFAULT;
    }

    /**
     * Map Platform.Sh environment variables to the values Symfony Flex expects.
     *
     * This is wrapped up into a function to avoid executing code in the global
     * namespace.
     */
    public function mapPlatformShEnvironment(): void
    {
        $config = new Config();

        if (!$config->inRuntime()) {
            if ($config->inBuild()) {
                // In the build hook we still need to set a fake Doctrine URL in order to
                // work around bugs in Doctrine.
                $this->setDefaultDoctrineUrl();
            }
            return;
        }

        $config->registerFormatter('doctrine', __CLASS__ . '::doctrineFormatter');

        // Set the application secret if it's not already set.
        // We force re-setting the APP_SECRET to ensure it's set in all of PHP's various
        // environment places.
        $secret = getenv('APP_SECRET') ?: $config->projectEntropy;
        static::setEnvVar('APP_SECRET', $secret);

        // Default to production. You can override this value by setting
        // `env:APP_ENV` as a project variable, or by adding it to the
        // .platform.app.yaml variables block.
        $appEnv = getenv('APP_ENV') ?: 'prod';
        static::setEnvVar('APP_ENV', $appEnv);

        // Map services as feasible.
        $this->mapPlatformShDatabase($this->dbName, $config);
        $this->mapPlatformShMongoDatabase($this->mongodbName, $config);

        // Set the Swiftmailer configuration if it's not set already.
        if (!getenv('MAILER_URL')) {
            $this->mapPlatformShSwiftmailer($config);
        }

        // Set the Symfony Mailer configuration if it's not set already.
        if (!getenv('MAILER_DSN')) {
            $this->mapPlatformShMailer($config);
        }
    }

    /**
     * Sets an environment variable in all the myriad places PHP can store it.
     *
     * @param string $name
     *   The name of the variable to set.
     * @param null|string $value
     *   The value to set.  Null to unset it.
     * @throws \RuntimeException when the environment variable could not be set
     */
    public static function setEnvVar(string $name, ?string $value): void
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

    protected function mapPlatformShSwiftmailer(Config $config)
    {
        $mailUrl = sprintf(
            '%s://%s:%d/',
            !empty($config->smtpHost) ? 'smtp' : 'null',
            !empty($config->smtpHost) ? $config->smtpHost : 'localhost',
            25
        );

        static::setEnvVar('MAILER_URL', $mailUrl);
    }

    protected function mapPlatformShMailer(Config $config)
    {
        $mailUrl = sprintf(
            '%s://%s:%d/',
            !empty($config->smtpHost) ? 'smtp' : 'null',
            !empty($config->smtpHost) ? $config->smtpHost : 'localhost',
            25
        );

        static::setEnvVar('MAILER_DSN', $mailUrl);
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
    public static function doctrineFormatter(array $credentials): string
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
            [$type, $version] = explode(':', $credentials['type']);
        } else {
            $type = $credentials['scheme'];
            $version = null;
        }

        // "mysql" is an alias for MariaDB, so use the same mapper for both.
        $mappers['mysql'] = __CLASS__ . '::doctrineFormatterMariaDB';
        $mappers['mariadb'] = __CLASS__ . '::doctrineFormatterMariaDB';
        $mappers['oracle-mysql'] = __CLASS__ . '::doctrineFormatterOracleMySQL';
        // The "Scheme" is pgsql, while the type is postgresql. Both end up in the same place.
        $mappers['pgsql'] = __CLASS__ . '::doctrineFormatterPostgreSQL';
        $mappers['postgresql'] = __CLASS__ . '::doctrineFormatterPostgreSQL';

        // Add a query suffix that is appropriate to the specific DB type to handle version information.
        return $dbUrl . $mappers[$type]($version);
    }

    public static function doctrineFormatterMariaDB(?string $version): string
    {
        $version = $version ?? static::DEFAULT_MARIADB_VERSION;

        $version = sprintf('mariadb-%s', $version);

        // Doctrine requires a full version number, even though it doesn't really matter what the patch version is,
        // except for MariaDB 10.2, where it needs a verison greater than 10.2.6 to avoid certain bugs.
        $version .= ($version === 'mariadb-10.2') ? '.12' : '.0';

        return sprintf('?charset=utf8mb4&serverVersion=%s', $version);
    }

    public static function doctrineFormatterOracleMySQL(?string $version): string
    {
        $version = $version ?? static::DEFAULT_MYSQL_VERSION;

        return sprintf('?charset=utf8mb4&serverVersion=%s', $version);
    }

    public static function doctrineFormatterPostgreSQL(?string $version): string
    {
        $version = $version ?? static::DEFAULT_POSTGRESQL_VERSION;

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
    protected function mapPlatformShDatabase(string $relationshipName, Config $config): void
    {
        if (!$config->hasRelationship($relationshipName)) {
            return;
        }

        static::setEnvVar('DATABASE_URL', $config->formattedCredentials($relationshipName, 'doctrine'));
    }

    /**
     * Sets a default Doctrine URL.
     *
     * Doctrine needs a well-formed URL string with a database version even in the build hook.
     * It doesn't use it, but it fails if it's not there.  This default meets the minimum
     * requirements of the format without actually allowing a connection.
     */
    protected function setDefaultDoctrineUrl(): void
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

        static::setEnvVar('DATABASE_URL', $dbUrl);
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
    protected function mapPlatformShMongoDatabase(string $relationshipName, Config $config): void
    {
        if (!$config->hasRelationship($relationshipName)) {
            return;
        }

        $credentials = $config->credentials($relationshipName);

        static::setEnvVar('MONGODB_SERVER', sprintf('mongodb://%s:%d', $credentials['host'], $credentials['port']));
        static::setEnvVar('MONGODB_DB', $credentials['path']);
        static::setEnvVar('MONGODB_USERNAME', $credentials['username']);
        static::setEnvVar('MONGODB_PASSWORD', $credentials['password']);
    }

}
