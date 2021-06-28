<?php

query("SELECT * FROM test WHERE pid={:=pid}", [ 'pid' => null ]);
query("UPDATE SET id=NULL WHERE pid={:=pid}", [ 'pid' => null ]);

