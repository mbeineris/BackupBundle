[![Build Status](https://travis-ci.org/mbeineris/BackupBundle.svg?branch=master)](https://travis-ci.org/mbeineris/BackupBundle) [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
# BackupBundle

This symfony bundle makes json backups from specified entities.

Requirements
============
- (Optional)Gaufrette bundle: https://github.com/KnpLabs/KnpGaufretteBundle

Installation
============

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require mabe/backup-bundle
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Mabe\BackupBundle\MabeBackupBundle(),
        );

        // ...
    }

    // ...
}
```

### Step 3: Configure
```yml
mabe_backup:
    jobs:
        # Job name can be anything except reserved names.
        # Job must have at least one entity and backup location configured.
        job1:
            entities:
                # Test1 entity will backup all entity properties
                AppBundle\Entity\Test1: ~
                # Test2 entity will backup only properties that have "backup" in @BackupGroups
                # NOTE: Groups are optional and their names are case sensitive
                AppBundle\Entity\Test2:
                    groups: ["backup"]
            # Backup files will be saved in local directory    
            local: /projects/backups/
        job2:
            entities:
                AppBundle\Entity\Test3:
                    groups: ["base64"]
            # Filesystem has to be configured based on gaufrette documentation    
            gaufrette:
                - backup_fs
```
If you are using groups:
```php
// ../src/Entity/Test1.php

use Mabe\BackupBundle\Annotations\BackupGroups;

class Test1
{
    /**
     * @ORM\Column(type="string", length=255)
     * @BackupGroups({"backup"})
     */
    private $firstName;
    
    ...
}
```

### Step 4: Symfony 4 only
Symfony 4 no longer registers bundle Commands from the Command folder as per https://github.com/symfony/symfony/blob/master/UPGRADE-4.0.md#httpkernel .
Register the command like this:
```yml
// config/services.yml
services:

    # Explicit command registration
    App\Command\BackupCommand:
        class: 'Mabe\BackupBundle\Command\BackupCommand'
        tags: ['console.command']
```

Usage
============
Run all configured backups:
```console
$ php bin/console mabe:backup
```
List jobs:
```console
$ php bin/console mabe:backup --list
```
You can also specify just the jobs you want to run like this:
```console
$ php bin/console mabe:backup job1 job2 job3
```
Help:
```console
$ php bin/console mabe:backup --help
```

Running tests
============
./vendor/bin/simple-phpunit
