# rkphplib
PHP library with template engine and wrapper classes to filesystem, mysql and other.

## Install

Install with composer in project directory

```
composer require rklib/rkphplib
```


## Examples

Autoload library via composer.

```
<?php

require 'vendor/autoload.php';

print $settings_TIMEZONE."\n";
print $settings_LANGUAGE."\n";
```

File and Dir example.

```
<?php

require_once('src/File.class.php');
require_once('src/Dir.class.php');

use rkphplib\File;
use rkphplib\Dir;

if (Dir::exists('src')) {
	echo File::load('composer.json');
}
```

Date calcuation.

```
<?php

require_once('src/DateCalc.class.php');

use rkphplib\DateCalc;

print "3rd month in german: ".DateCalc::monthName(3)."\n";

$settings_LANGUAGE = 'en';
print "3rd month in english: ".DateCalc::monthName(3)."\n";

$sql_date = '2016-07-18 15:30:00';
print "SQL Date $sql_date: de_format=".DateCalc::formatDateTimeStr('de', $sql_date, 'sql').", timestamp=".DateCalc::sqlTS('2016-07-18 15:30:00')."\n";
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

