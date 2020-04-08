<?php

$func = 'rkphplib\lib\conf2kv';

function get_example($n) {
	$base = __DIR__.'/'.sprintf('example_%02d', $n);
	$conf = trim(file_get_contents($base.'.conf'));
	$ok = trim(file_get_contents($base.'.ok'));
	return [ $conf, $ok ];
}

$test = [
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
	['a=@1 a, b, c|#|b=@1 a, "b, c"|#|c=@1 a\, b," c"', '{"a":["a","b","c"],"b":["a","\"b","c\""],"c":["a\\\\","b"," c"]}'],
	get_example(1),
	get_example(2),
	get_example(3),
	get_example(4),
	get_example(5)
];

