# wp-fallback-plugins-dir

Load all WordPress plugins in a specified directory, unless they are
loaded elsewhere.

## Why?

Look, I'm not saying that you _should_ bundle other plugins with your
theme/plugin, but if your deployment process only handles theme deployment
rather than a full WordPress deployment then you could give it a try.

1. Users can't disable the bundled plugins. They can still install another
   version of the same plugin and load it instead, which may be useful
   in case of security updates after the theme is no longer being
   developed.
2. The fallback plugins are still visible in the Admin interface.
   This can help future developers (including your future self) figure
   out what is going on.
3. If a user would install some incompatible plugin, the first action
   can now be to bulk-deactivate all plugins. The bundled plugins are
   still active after this operation, so it should not break vital site
   functionality.
4. Reduce the amount of clicking around in admin panels after deploys
   or when setting up a new development environments.

## Installation

Using `composer`:

```sh
$ composer require ojohnny/wp-fallback-plugins-dir
```

## Usage

Assuming you are using `composer` and have installed `composer/installers`
and a couple of WordPress plugins (possibly from wpackagist), then your
`functions.php` could look something like this:

```php
<?php

require_once __DIR__ . "/vendor/autoload.php";

\WpFallbackPluginsDir\LoadPlugins::from_path(__DIR__ . "/wp-content/plugins");
```

If you want to use this inside a plugin, perhaps it is better to do this
inside an appropriate `add_action()`, such as `plugins_loaded`.
