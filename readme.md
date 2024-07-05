# PHP Http proxy

This is for my personal use, pull request is not welcome 🤣.

## Install
```bash
composer install
```

## Usage

```bash
php start.php start
```

If you want to run as background daemon
```bash
php start.php start -d
```

If you want to stop
```bash
php start.php stop
```

Check status
```bash
php start.php status
```

If you want to set username and password, please remember alway put `-u` and `-p` before `start`
```bash
php start.php -u bangnokia -p 123456 start -d
```