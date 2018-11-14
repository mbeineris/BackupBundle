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
                # Test2 entity will backup only given properties
                AppBundle\Entity\Test2:
                    properties: ["username", "birthDate"]
            # Service id        
            target: "app.backup.service"
        job2:
            entities:
                # Test3 entity will use JMS groups
                # NOTE: Groups are optional and their names are case sensitive
                AppBundle\Entity\Test3:
                    groups: ["backup"]
                target: "app.backup.local_saver"
```
### Step 4: Creating target service for saving
Through many versions of the bundle, saving evolved from specifying local directories and gaufrette
filesystems to creating a saving service. This allows you to create your own saving way whether it is local, gaufrette, database, redis or anything you decide. All you have to do is implement SaverInterface in your logic:
```yml
    app.backup.local_saver:
        class: App\Backup\Saver
        public: true # Only for Symfony 4
```
Your local directory saver class could look like this:
```php
// .../App/Backup/Saver.php

use Mabe\BackupBundle\Saver\SaverInterface;

class Saver implements SaverInterface
{
    public function save($json, $filename)
    {
        file_put_contents('/your/directory/'.$filename, $json);
    }
}
```
If you would like to easily use gaufrette, there is an interface for that. you only need to pass your filesystem like this:
```yml
    app.backup.saver:
        class: Mabe\BackupBundle\Saver\GaufretteSaver
        arguments: ["your_gaufrette_fs", "@knp_gaufrette.filesystem_map"]
        public: true # Only for Symfony 4
```

### Step 5: Symfony 4 only
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
        // send mail, etc..
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
