# Ultimania Xaseco Plugin

## Development

### Setup

- Git clone TODO
- `composer install`
- Get a clean xaseco installation and copy it into `xaseco` folder. This helps your IDE with auto-completion.

### Running this with xaseco directly
If you want to test this in xaseco directly, do the following:
- Create a symbolic link of [src/plugin.ultimania.php](src/plugin.ultimania.php) into your xaseco plugins folder.
- Create a symbolic link of [src/ultimania.xml](src/ultimania.xml) into your xaseco folder
- In your plugins.xml, include `<plugin>plugin.ultimania.php</plugin>`

### Unit tests
Run `composer run-script phpunit`

### Static analysis
This project uses PHPStan. Run it with `composer run-script phpstan`

## Building
TODO
