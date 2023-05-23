# Shopware plugin management

This package provides a simple way to manage your Shopware plugins. It can handle the installation, update and removal
of plugins.

The package provides a new command `netzarbeiter:plugin:manage` which can be used to manage your plugins. The command
takes a JSON file with plugins that should be active in your Shopware installation.

## Installation

Make sure Composer is installed globally, as explained in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Applications that use Symfony Flex

Open a command console, enter your project directory and execute:

```console
$ composer require <package-name>
```

### Applications that don't use Symfony Flex

#### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the following command to download the latest stable
version of this bundle:

```console
$ composer require <package-name>
```

#### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Netzarbeiter\Shopware\PluginManagement\NetzarbeiterShopwarePluginManagementBundle::class => ['all' => true],
];
```

## Usage

```bash
bin/console netzarbeiter:plugin:manage <file>
```

## `plugins.json`

The command takes a JSON file with the following schema:

```json
{
  "<plugin name>": {
    "active": true|false,
    "update": true|false|"force"
  }
}
```

The command will install all plugins in that file, activate them if `active` is `true` and update them if `update` is
`true` and an update is available or if `update` is set to `"force"` (useful for local plugins).

The command will afterwards uninstall all plugins that are installed in your Shopware installation, but are not in the
`plugins.json` file.

## Automation

You can use the command in your CI/CD pipeline to automate the installation of plugins. To do so, you can add the
command as a post-update script to your `composer.json` file:

```json
{
  "scripts": {
    "post-update-cmd": [
      "@php bin/console netzarbeiter:plugin:manage plugins.json"
    ]
  }
}
```
