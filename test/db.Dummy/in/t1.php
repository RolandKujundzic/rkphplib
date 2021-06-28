<?php

query("SELECT * FROM test");
query("SELECT {:=_col} FROM {:=_table}", [ '_col' => 'id', '_table' => 'test' ]);
query("SELECT {:=col} FROM {:=_table}", [ 'col' => '1,2; DROP ALL TABLES; --', '_table' => "a'n" ]);
query("SELECT {:=col} FROM {:=_table}", [ 'col' => 'id', '_table' => '1,2; DROP ALL TABLES; --' ]);
query("SELECT * FROM x WHERE id IN ({:=id})", [ 'id' => 7 ]);
query("SELECT * FROM x WHERE id IN ({:=_id})", [ '_id' => '1,3,empty' ]);
query("SELECT * FROM x WHERE id IN ({:=id})", [ 'id' => [ 1, 3, 'empty' ] ]);

