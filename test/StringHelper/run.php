<?php

global $th;

if (!isset($th)) {
	require_once dirname(dirname(__DIR__)).'/src/TestHelper.php';
	$th = new rkphplib\TestHelper();
}

$th->load('src/StringHelper.class.php');

$html = <<<EOF
<!-- COMMENT -->
some text
<br><div class="test box" id="x1">
<a href="../link.html">Home </a>  |  <a href="https://another/link.html"> Somewhere </a>
</div>
Some more<br>text<br/>
EOF;

$out = \rkphplib\StringHelper::removeHtmlWhiteSpace($html);
$ok = '<!-- COMMENT -->some text<br><div class="test box" id="x1"><a href="../link.html">Home</a>|'.
	'<a href="https://another/link.html">Somewhere</a></div>Some more<br>text<br/>';
$th->compare("StringHelper::removeHtmlWhiteSpace(html)", [ $out ], [ $ok ]);

$out = \rkphplib\StringHelper::removeHtmlAttributes($html);
$ok = <<<EOF
<!-- COMMENT -->
some text
<br><div>
<a>Home </a>  |  <a> Somewhere </a>
</div>
Some more<br>text<br>
EOF;
$th->compare("StringHelper::removeHtmlAttributes(html)", [ $out ], [ $ok ]);

$out = \rkphplib\StringHelper::removeHtmlTags($html, '<br>');
$ok = <<<EOF

some text
<br>
Home   |   Somewhere 

Some more<br>text<br/>
EOF;
$th->compare("StringHelper::removeHtmlTags(html, allow)", [ $out ], [ $ok ]);

$out = \rkphplib\StringHelper::url('SEO Link - öäüß ÖÄÜ & "test"');
$ok = 'seo-link-oeaeuess-oeaeue-test';
$th->compare("StringHelper::url(link)", [ $out ], [ $ok ]);


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

$ok = '<img src="source.jpg" alt="" title=""/><img alt="" title="" src="nix.png"/><img data-a="data-a" data-b="data-b"/>';
$th->compare("StringHelper->nextTag()", [ $out ], [ $ok ]);
