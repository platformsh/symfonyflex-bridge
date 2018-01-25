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
