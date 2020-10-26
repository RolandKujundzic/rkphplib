<?php

$x = \rkphplib\File::nfo('out/t2.nfo', 'run.php');

$x['lastModified'] = 'unset';
print_r($x);

