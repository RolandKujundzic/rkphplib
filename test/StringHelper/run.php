<?php

global $th;

if (!isset($th)) {
	require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
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

$out = rkphplib\StringHelper::removeHtmlWhiteSpace($html);
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


