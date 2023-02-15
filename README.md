> **Archived template:** This Symfony Flex Bridge has been archived and is no longer the recommended tool for deployment. Users should consult the new and official [Platform.sh template provided from Symfony](https://github.com/symfonycorp/platformsh-symfony-template) and its use of Configurator. 
> 
# Symfony Flex bridge for Platform.sh

This simple bridge library connects a Symfony Flex-based application to [Platform.sh](https://platform.sh/).  In the typical case it should be completely fire-and-forget.

Symfony Flex expects all configuration to come in through environment variables with specific names in a specific format.  Platform.sh provides configuration information as environment variables in a different specific format.  This library handles mapping the Platform.sh variables to the format Symfony Flex expects for common values.

## Usage

Simply require this package using Composer.  When Composer's autoload is included this library will be activated and the environment variables set.  As long as that happens before Symfony bootstraps its configuration (which it almost certainly will) everything should work fine with no further user-interaction necessary.

```
composer require platformsh/symfonyflex-bridge
```

## Mappings performed

* If a Platform.sh relationship named `database` is defined, it will be taken as an SQL database and mapped to the `DATABASE_URL` environment variable for Symfony Flex.  (Note: Due to a bug in Doctrine, the code currently assumes MariaDB 10.2 as the service version.  If that Doctrine bug is ever resolved this hard-coding can be removed.)

* The Symfony Flex `APP_SECRET` is set based on the `PLATFORM_PROJECT_ENTROPY` variable, which is provided for exactly this purpose.

* The `MAILER_URL` variable is set based on the `PLATFORM_SMTP_HOST` variable.  That will be used by SwiftMailer if it is installed.  If not installed this value will be safely ignored.

* If no `APP_ENV` value is set, it will default to `prod`.

## Elasticsearch

If a Platform.sh relationship named `elasticsearch` is defined, it will be taken as an Elasticsearch index and mapped to appropriate environment variables.  Most Elasticsearch packages for Symfony do not have a standard  naming convention for environment variables so you will need to modify your Symfony configuration to read them.

For the common Elastica library, you would add the following to your Symfony `config/services.yaml` file:

```yaml
# config/services.yaml
parameters:
  es_host: '%env(ELASTICSEARCH_HOST)%'
  es_port: '%env(ELASTICSEARCH_PORT)%'
```

And then you can reference those parameters in your Elastica configuration file:

```yaml
# config/packages/fos_elastica.yaml
fos_elastica:
    clients:
        default: { host: '%es_host%', port: '%es_port%' }
```

## MongoDB

If a Platform.sh relationship named `mongodatabase` is defined, it will be taken as a Doctrine ODM database and mapped to the appropriate environment variables.  Note that you may still need to reference those environment variables in your configuration if they are not defined by default.  See the [DoctrineMongoDBBundle](https://symfony.com/doc/master/bundles/DoctrineMongoDBBundle/index.html) documentation for more details.

Generally, placing the following in your `doctrine_mongodb.yaml` file should be sufficient:

```yaml
# config/packages/doctrine_mongodb.yaml
doctrine_mongodb:
    connections:
        default:
            server: '%env(MONGODB_SERVER)%'
            options: { username: '%env(MONGODB_USERNAME)%', password: '%env(MONGODB_PASSWORD)%', authSource: '%env(MONGODB_DB)%' }
    default_database: '%env(MONGODB_DB)%'
```

## RabbitMQ

If a Platform.sh relationship named `rabbitmqqueue` is defined, it will be taken as a RabbitMQ messenger backend and mapped to the appropriate environment variable.

## Solr

If a Platform.sh relationship named `solr` is defined, it will be taken as a Solr index and mapped to appropriate environment variables.

For the common uses, you would add the following to your Symfony `config/services.yaml` file:

```yaml
# config/services.yaml
parameters:
    solr_dsn: '%env(SOLR_DSN)%'
    solr_core: '%env(SOLR_CORE)%'
```

And then you can reference those parameters in your configuration file:

```yaml
# config/packages/search_engine_solr.yaml
search_engine_solr:
    endpoints:
        endpoint0:
            dsn: '%solr_dsn%'
            core: '%solr_core%'
    connections:
        default:
            entry_endpoints:
                - endpoint0
```


## Redis Cache

If a Platform.sh relationship named `rediscache` is defined, it will be taken as a the storage engine for a cache pool.

For typical use you will need to define a file looking like this:

```yaml
# config/packages/framework.yaml
framework:
    cache:
        app: cache.adapter.redis
        system: cache.adapter.redis
        default_redis_provider: "%env(CACHE_URL)%"

```
For more details see [here](https://symfony.com/doc/current/components/cache/adapters/redis_adapter.html)

## Redis session storage

If a Platform.sh relationship named `redissession` is defined, it will be taken as a the storage engine for symfony session.

For typical use you will need to add a couple of service definitions which looks like this:
```yaml
# config/packages/framework.yaml
framework:
    session:
        handler_id: '%env(SESSION_REDIS_URL)%'
```


For more details see [here](https://symfony.com/doc/current/session/database.html#store-sessions-in-a-key-value-database-redis)
