[![Build Status](https://travis-ci.org/mbeineris/BackupBundle.svg?branch=master)](https://travis-ci.org/mbeineris/BackupBundle) [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
# BackupBundle

This symfony bundle makes json backups from specified entities.

Requirements
============
- Gaufrette bundle: https://github.com/KnpLabs/KnpGaufretteBundle
- JMSSerializer bundle: https://github.com/schmittjoh/JMSSerializerBundle

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
# Configure serializer to use Default group
fos_rest:
    serializer:
        groups: [Default]
        
mabe_backup:
    jobs:
        # Job name can be anything except reserved names
        job1:
            entities:
                # Test1 entity will backup all default and backup groups
                # NOTE: Group names are case sensitive
                AppBundle\Entity\Test1: groups: ["Default", "backup"]
                # Test2 entity will backup default group only
                AppBundle\Entity\Test2: ~
            # Backup files will be saved in local directory    
            local: /projects/backups/Projects/Backup/
        job2:
            entities:
                AppBundle\Entity\Test3: ~
            # Filesystem has to be configured based on gaufrette documentation    
            gaufrette:
                - backup_fs
```
Running tests
============
./vendor/bin/simple-phpunit
