<?php

namespace rkphplib\lib;


/**
 * Escape html. Replace [<], [>] and ["] with [&lt;], [&gt;] and [&quot;].
 * Use instead of htmlspecialchars, because &#43; is not converted to &amp;#43;
 * If $js is true escape ['] with [\'].
 */
function htmlescape($html, $js = false) {
	$res = str_replace([ '<', '>', '"' ], [ '&lt;', '&gt;', '&quot;' ], $html);

	if ($js) {
		$res = str_replace("'", "\'", $res);
	}

	return $res;
}

