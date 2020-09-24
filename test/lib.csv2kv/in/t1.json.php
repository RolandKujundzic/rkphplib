<?php

$test = [
	'rkphplib\lib\csv2kv',
	['@→oef” = pwoief=j=', '{"@→oef”":"pwoief=j="}'],
	['first_name = Peter|#|last_name= Müller', '{"first_name":"Peter","last_name":"Müller"}'],
	[' x y z ', '["x y z"]'],
	['" x y z "', '[" x y z "]'],
	['a|#|', '["a",""]'],
  ['|#| b ', '["","b"]'],
  [' |#| c |#| ', '["","c",""]'],
  ['""|#|" d |#|""', '["","d |#|\""]'],
  ['a|#|50|#|=abce', '["a","50","=abce"]'],
	['a= 12 |#|', '{"a":"12"}'],
	['a=A', '{"a":"A"}'],
	['abc="A""BC"'."\n|#|\n\t b =  B B B", '{"abc":"A\"BC","b":"B B B"}'],
	['a=1|#|b=2|#|c=3|#|', '{"a":"1","b":"2","c":"3"}'],
];

