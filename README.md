# Symfony Flex bridge for Platform.sh

[![CircleCI Status](https://circleci.com/gh/platformsh/symfonyflex-bridge.svg?style=shield&circle-token=:circle-token)](https://circleci.com/gh/platformsh/symfonyflex-bridge)

This simple bridge library connects a Symfony Flex-based application to [Platform.sh](https://platform.sh/).  In the typical case it should be completely fire-and-forget.

Symfony Flex expects all configuration to come in through environment variables with specific names in a specific format.  Platform.sh provides configuration information as environment variables in a different specific format.  This library handles mapping the Platform.sh variables to the format Symfony Flex expects for common values.

## Usage

Simply require this package using Composer.  When Composer's autoload is included this library will be activated and the environment variables set.  As long as that happens before Symfony bootstraps its configuration (which it almost certainly will) everything should work fine with no further user-interaction necessary.

```
composer require platformsh/symfonyflex-bridge
```

## Mappings performed

* If a Platform.sh relationship named `database` is defined, it will be taken as an SQL database and mapped to the `DATABASE_URL` environment variable for Symfony Flex.  (Note: Due to a bug in Doctrine, the code currently assumes MariaDB 10.2 as the service version.  If that Doctrine bug is ever resolved this hard-coding can be removed.)

* If a Platform.sh relationship named `mongodatabase` is defined, it will be taken as a Doctrine ODM database and mapped to the appropriate environment variables.  Note that you may still need to reference those environment variables in your configuration if they are not defined by default.  See the [DoctrineMongoDBBundle](https://symfony.com/doc/master/bundles/DoctrineMongoDBBundle/index.html) documentation for more details.

* The Symfony Flex `APP_SECRET` is set based on the `PLATFORM_PROJECT_ENTROPY` variable, which is provided for exactly this purpose.

* The `MAILER_URL` variable is set based on the `PLATFORM_SMTP_HOST` variable.  That will be used by SwiftMailer if it is installed.  If not installed this value will be safely ignored.

* If no `APP_ENV` value is set, it will default to `prod`.
