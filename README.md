# rkphplib
PHP library with template engine and wrapper classes to filesystem, mysql and other.

## Install

Install with composer in project directory

```
composer require rklib/rkphplib
```


## Examples

```
<?php

# composer autoload:
# require 'vendor/autoload.php';

# php include:
require_once('src/File.class.php');
require_once('src/Dir.class.php');

use rkphplib\File;
use rkphplib\Dir;

if (Dir::exists('src')) {
	echo File::load('composer.json');
}
```


## Requirements

- PHP 5.5


## Documentation

Created with [ApiGen](https://github.com/ApiGen/ApiGen). Re-create documentation with:

```sh
vendor/apigen/apigen/bin/apigen generate -s ./src -d ./docs/api
```

ApiGen was installed with
```sh
php composer.phar require --dev apigen/apigen
```

