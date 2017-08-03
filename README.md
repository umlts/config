# Config

Configuration loader class.

The class loads configuration files in JSON, YAML or INI format. And it 
adds the possibility to use the command line to change the configuration
files loaded.

## Usage

### Basics

The Config class looks for the default configuration file in 
'config/config.json', 'config/config.yaml', and 'config/config.ini'.

```php
use Umlts\Config\Config;
$config = new Config( __DIR__ );

// Returns the setting.
// Config throws an InvalidArgumentException if the setting does
// not exist.
echo $config->get( 'setting_name' );
echo $config->get( 'settings_set/setting_name' );

// Echoes $default_value if 'setting_name' isn't set.
echo $config->get( 'setting_name', $default_value );

// Check if setting exists:
if ( $config->exists( 'setting' ) ) { echo 'Setting exists!'; }
```

### Loading config files

The class tries to load the default config files at first. More config
files can be added. The settings from the new config file overwrite
the existing ones.

```php
$config
    ->load( 'path/to/config.json' )
    ->load( 'path/to/config.yaml' )
    ->load( 'path/to/config.ini' );
```

- **Comments in JSON**: JSON files may have comments indicated by a leading 
```#```. The comments will be removed before the content gets parsed.
- **Stream wrappers**: The config files are loaded thru stream wrappers.
It is possible to load a config file from a HTTP server or an FTP server.

It is possible to prevent the class from loading the default config
files:

```php
$config_wo_default = new Config( __DIR__, /* $ignore_default = */ TRUE );
```

### Namespaces

A namespace can be set at any time. The config object gives back the
values inside this namespace:

```php
$config->set( 'namespace/setting', 10 );

echo $config->get( 'namespace/setting' );   // Returns 10

$config->setNamespace( 'namespace' );
echo $config->get( 'setting' ); // Returns also 10
```
The namespace can be overriden by a leading slash:

```php
$config->setNamespace( 'namespace' );
echo $config->get( '/namespace/setting' );  // Returns 10, too
```

### Command line options

The Config class also takes command line arguments in account:

```sh
php config-test.php --config:ignore-default --config:file=/tmp/test.config.json
```

- *config:ignore-default* prevents the class from loading the default
  config files.
- *config:file* loads an (additional) config file.
