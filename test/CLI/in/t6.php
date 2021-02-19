<?php

syntax('test.php sss', [ 'directory' ], [ '@1:dir' ]);
syntax('test.php in', [ 'directory' ], [ '@1:dir' ]);
syntax('test.php abc --help', [ 'directory' ], [ '@1:dir' ]);
syntax('font.php Arial', [ 'fontname', '?parameter' ], [ '@docroot', '#1:Poppins', '#2:300,300i' ]);
syntax('font.php --help', [ 'fontname', '?parameter' ], [ '@docroot', '#1:Poppins', '#2:300,300i' ]);

