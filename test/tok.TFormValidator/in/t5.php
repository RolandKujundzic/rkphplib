<?php

\rkphplib\tok\TFormValidator::$conf_file = __DIR__.'/../out/t5.conf';
$tf = new \rkphplib\tok\TFormValidator();
print file_get_contents(\rkphplib\tok\TFormValidator::$conf_file);

