# rkphplib
PHP library with template engine and wrapper classes to filesystem, mysql and other.

## examples

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

