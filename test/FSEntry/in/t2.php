<?php

suffix_list([ '.inc.php' ]);
check('a/main.inc.php');
check('main.inc.php');
check('config/settings.php');

suffix_list([ '~/settings.php' ]);
check('a/b.php');
check('settings.php');
check('config/settings.php');

