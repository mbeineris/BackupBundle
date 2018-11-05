[![Build Status](https://travis-ci.org/mbeineris/BackupBundle.svg?branch=master)](https://travis-ci.org/mbeineris/BackupBundle) [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
# BackupBundle

This symfony bundle makes json backups from specified entities.

Requirements
============
- (Optional) Gaufrette bundle: https://github.com/KnpLabs/KnpGaufretteBundle

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

### Step 2: Enable the Bundle (can skip if you are using Symfony Flex)

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
                # Test2 entity will use JMS groups
                # NOTE: Groups are optional and their names are case sensitive
                AppBundle\Entity\Test2:
                    groups: ["backup"]
            # Backup files will be saved in local directory    
            local: /projects/backups/
        job2:
            entities:
                # Test3 entity will backup only given properties
                AppBundle\Entity\Test3:
                    properties: ["username", "birthDate"]
            # Filesystem has to be configured based on gaufrette documentation    
            gaufrette:
                - backup_fs
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
Advanced Usage
============
You can create a listener to modify your entities on pre_backup event or do something on backup_finished (ex. send mail).
```php

// src/AppBundle/Listener/BackupListener.php

namespace AppBundle\Listener;

use Mabe\BackupBundle\Event\BackupEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BackupListener implements EventSubscriberInterface
{

    public function preBackup(BackupEvent $event)
    {
        $object = $event->getObject();
        $job = $event->getActiveJob();
        if($object instanceof User) {
            if (!$object->isEnabled()) {
                // You can skip object backup if your conditions are not met
                $event->setSerialize(false);
            }
        }
    }

    public function postBackup(BackupEvent $event)
    {
        // do something
    }

    public function backupFinished(BackupEvent $event)
    {
        $finishedJobs = $event->getJobs();
        // send mail
    }

    public static function getSubscribedEvents()
    {
        return array(
            BackupEvent::PRE_BACKUP => 'preBackup',
            BackupEvent::POST_BACKUP => 'postBackup',
            BackupEvent::BACKUP_FINISHED => 'backupFinished'
        );
    }
}
```
...and register serivce:
```yml
# ..app/config/services.yml

services:
    app.mabe_backup.listener:
        class: AppBundle\Listener\BackupListener
        tags:
            - { name: kernel.event_subscriber }
```

Running tests
============
./vendor/bin/simple-phpunit
