<?php

syntax('test.php Herr', [ 'gender' ], [ '@1:enum:Herr:Frau' ]);
syntax('test.php Mr divorced', [ 'Mr|Mrs', 'married|single' ], [ '@1:enum', '@2:enum' ]);

\rkphplib\CLI::$desc = 'App Description';
syntax('test.php', [], [ '@docroot' ]);

