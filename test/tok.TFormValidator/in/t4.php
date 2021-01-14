<?php

\rkphplib\tok\TFormValidator::$conf_file = __DIR__.'/../out/t4.json';
$tf = new \rkphplib\tok\TFormValidator();
print file_get_contents(\rkphplib\tok\TFormValidator::$conf_file);

