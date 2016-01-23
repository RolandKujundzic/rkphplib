# rkphplib
PHP library with template engine and wrapper classes to filesystem, mysql and other.

## Install

Install with composer in project directory

```sh
composer require rklib/rkphplib
```


## Examples

Autoload library via composer.

```php
<?php

require 'vendor/autoload.php';

print $settings_TIMEZONE."\n";
print $settings_LANGUAGE."\n";
```

File and Dir example.

```php
<?php

require_once('src/File.class.php');
require_once('src/Dir.class.php');

use rkphplib\File;
use rkphplib\Dir;

if (Dir::exists('src')) {
	echo File::load('composer.json');
}
```

Date calculation.

```php
<?php

require_once('src/DateCalc.class.php');

use rkphplib\DateCalc;

print "3rd month in german: ".DateCalc::monthName(3)."\n";

$settings_LANGUAGE = 'en';
print "3rd month in english: ".DateCalc::monthName(3)."\n";

$sql_date = '2016-07-18 15:30:00';
print "SQL Date $sql_date: de_format=".DateCalc::formatDateTimeStr('de', $sql_date, 'sql').", timestamp=".DateCalc::sqlTS('2016-07-18 15:30:00')."\n";
```

Template parser. If **{action:param}body{:action}** is detected the result of **Plugin->tok_action(param, body)** callback will be inserted. 
Parser is bottom-up but can be changed by plugin to top-down.

```php
<?php

require_once('src/Tokenizer.class.php');

class Plugin {
	private $n = 0;
	public $tokPlugin = array('x' => 6); // change 6 to 0 or 2 and compare different output
	public function tok_x($param, $arg) { $this->n++; return "X".$this->n."($param)[$arg]"; }
}

$txt = 'a1{x:p1}a2{x:p2}a3{:x}a4{:x}a5{x:p3}a6{:x}';

$tok = new rkphplib\Tokenizer();
$tok->setPlugin(new Plugin());
$tok->setText($txt);

print "\nInput: $txt\nOutput: ".$tok->toString()."\n\n";
```

## Requirements

- PHP 5.5


## Documentation

Create with [ApiGen](https://github.com/ApiGen/ApiGen):

```sh
vendor/apigen/apigen/bin/apigen generate -s ./src -d ./docs/api
```

If composer or ApiGen are not installed run:

````sh
./build.sh composer
./build.sh docs 
```

