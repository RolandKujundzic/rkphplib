<?php

$test = [
	'rkphplib\lib\conf2kv',
	['@→oef” = pwoief=j=', '{"@→oef”":"pwoief=j="}'],
	['first_name = Peter|#|last_name= Müller', '{"first_name":"Peter","last_name":"Müller"}'],
	[' x y z ', '["x y z"]'],
	['" x y z "', '[" x y z "]'],
	['a|#|', '["a"]'],
	['|#| b ', '["b"]'],
	[' |#| c |#| ', '["c"]'],
	['""|#|" d |#|""', '["","\" d",""]'],
	['a|#|50|#|=abce', '["a","50","=abce"]'],
	['a= 12 |#|', '{"a":"12"}'],
	['a=A|#|b="B|#|c=C|#|d=D"|#|e=e"e', '{"a":"A","b":"\"B","c":"C","d":"D\"","e":"e\"e"}'],
	['abc="A""BC"'."\n|#|\n\t b =  B B B", '{"abc":"A\"\"BC","b":"B B B"}'],
	['a=1|#|b=2|#|c=3|#|', '{"a":"1","b":"2","c":"3"}'],
	['a=@1 a, b, c|#|b=@1 a, "b, c"|#|c=@1 a\, b," c"', '{"a":["a","b","c"],"b":["a","\"b","c\""],"c":["a\\\\","b"," c"]}']
];

