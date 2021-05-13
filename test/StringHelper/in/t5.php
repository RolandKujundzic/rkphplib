<?php

$html_str = <<<END
<img src="source.jpg"
	alt=""
	title=""
	/><IMG alt='' title='' src='nix.png'> <img
data-a	data-b >
<script>
	x = '<img ' + '>';
</script>
END;

$html = new \rkphplib\StringHelper($html_str);
$tag = new \rkphplib\HtmlTag('img');

$out = '';
while (($tag = $html->nextTag($tag))) {
	$out .= $tag->toString(); 
}

print $out;

