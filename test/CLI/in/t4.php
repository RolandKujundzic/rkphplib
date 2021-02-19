<?php

syntax('test.php', [ 'action' ]);
syntax('test.php abc', [ 'action' ]);
syntax('test.php abc', [ 'path/to/config.json' ], [ '@1:file' ]);
syntax('check.php run.php', [ 'script.php' ], [ '@1:file' ]);
syntax('convert xyz.png', [ 'image.jpg' ], [ '@1:file', '@1:suffix:.jpg|.jpeg' ]);

