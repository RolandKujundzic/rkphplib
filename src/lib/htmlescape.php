<?php

namespace rkphplib\lib;


/**
 * Escape html. Replace [<], [>] and ["] with [&lt;], [&gt;] and [&quot;].
 * Use instead of htmlspecialchars, because &#43; is not converted to &amp;#43;
 * 
 * @param string $html
 * @param boolean $js if true escape ' with \'
 * @return string
 */
function htmlescape($html, $js = false) {
	$res = str_replace([ '<', '>', '"' ], [ '&lt;', '&gt;', '&quot;' ], $html);

	if ($js) {
		$res = str_replace("'", "\'", $res);
	}

	return $res;
}

